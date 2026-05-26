'use strict';

/**
 * GastroVoice Media Bridge
 *
 * Bridges PBX audio (FreeSWITCH / Asterisk) to OpenAI Realtime API.
 * - WebSocket server on WS_PORT (default 8765) for FreeSWITCH mod_audio_fork
 * - TCP server on TCP_PORT (default 9093) for Asterisk AudioSocket
 *
 * Each inbound call gets its own OpenAI Realtime WebSocket session.
 * Tool calls (query_menu, create_reservation, etc.) are forwarded to the backend API.
 */

const WebSocket = require('ws');
const net = require('net');
const http = require('http');
const https = require('https');

// ── Config ──────────────────────────────────────────────────────────────────

const CONFIG = {
  wsPort: parseInt(process.env.WS_PORT || '8765', 10),
  tcpPort: parseInt(process.env.TCP_PORT || '9093', 10),
  apiUrl: process.env.GASTROVOICE_API_URL || 'http://backend:8080',
  openaiKey: process.env.OPENAI_API_KEY || '',
  realtimeModel: process.env.OPENAI_REALTIME_MODEL || 'gpt-4o-mini-realtime',
  defaultVoice: process.env.DEFAULT_VOICE || 'coral',
  restaurantId: process.env.DEFAULT_RESTAURANT_ID || '',
  restaurantName: process.env.DEFAULT_RESTAURANT_NAME || 'el restaurante',
};

// ── Audio Resampling Helpers ────────────────────────────────────────────────

/** Upsample PCM16 buffer from 8 kHz to 24 kHz (3× linear interpolation). */
function upsample8to24(buf) {
  const samples = buf.length / 2;
  const out = Buffer.alloc(samples * 3 * 2);
  for (let i = 0; i < samples; i++) {
    const s0 = buf.readInt16LE(i * 2);
    const s1 = i + 1 < samples ? buf.readInt16LE((i + 1) * 2) : s0;
    out.writeInt16LE(s0, i * 6);
    out.writeInt16LE(Math.round(s0 + (s1 - s0) / 3), i * 6 + 2);
    out.writeInt16LE(Math.round(s0 + (2 * (s1 - s0)) / 3), i * 6 + 4);
  }
  return out;
}

/** Downsample PCM16 buffer from 24 kHz to 8 kHz (take every 3rd sample). */
function downsample24to8(buf) {
  const samples = buf.length / 2;
  const outSamples = Math.floor(samples / 3);
  const out = Buffer.alloc(outSamples * 2);
  for (let i = 0; i < outSamples; i++) {
    out.writeInt16LE(buf.readInt16LE(i * 3 * 2), i * 2);
  }
  return out;
}

// ── Backend API Helper ──────────────────────────────────────────────────────

function backendRequest(method, path, body) {
  return new Promise((resolve, reject) => {
    const url = new URL(path, CONFIG.apiUrl);
    const isHttps = url.protocol === 'https:';
    const lib = isHttps ? https : http;
    const payload = body ? JSON.stringify(body) : null;

    const req = lib.request(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        ...(payload ? { 'Content-Length': Buffer.byteLength(payload) } : {}),
      },
    }, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch { resolve(data); }
      });
    });

    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

// ── Session Config from Backend ─────────────────────────────────────────────

async function getSessionConfig(restaurantName, voice) {
  try {
    const config = await backendRequest('GET',
      `/api/voice/telephony/session-config?restaurantName=${encodeURIComponent(restaurantName)}&voice=${encodeURIComponent(voice)}`
    );
    return config;
  } catch (err) {
    console.error('[Bridge] Failed to get session config from backend:', err.message);
    return null;
  }
}

// ── Tool Call Handler ───────────────────────────────────────────────────────

async function handleToolCall(name, args) {
  console.log(`[Bridge] Tool call: ${name}`, JSON.stringify(args));

  try {
    switch (name) {
      case 'query_menu': {
        const menu = await backendRequest('GET',
          `/api/menus/active?restaurantId=${encodeURIComponent(CONFIG.restaurantId)}`
        );
        return JSON.stringify(menu);
      }
      case 'check_availability': {
        const result = await backendRequest('GET',
          `/api/reservations/availability?date=${args.date}&timeSlot=${args.timeSlot}&numPeople=${args.numPeople}`
        );
        return JSON.stringify(result);
      }
      case 'create_reservation': {
        const result = await backendRequest('POST', '/api/reservations', {
          restaurantId: CONFIG.restaurantId,
          ...args,
        });
        return JSON.stringify(result);
      }
      case 'create_order': {
        const result = await backendRequest('POST', '/api/orders', {
          restaurantId: CONFIG.restaurantId,
          source: 'phone',
          ...args,
        });
        return JSON.stringify(result);
      }
      default:
        return JSON.stringify({ error: `Unknown tool: ${name}` });
    }
  } catch (err) {
    console.error(`[Bridge] Tool call error (${name}):`, err.message);
    return JSON.stringify({ error: err.message });
  }
}

// ── OpenAI Realtime Connection ──────────────────────────────────────────────

class OpenAIRealtimeSession {
  constructor(callId, onAudio, onEnd) {
    this.callId = callId;
    this.onAudio = onAudio; // callback(pcm16_24khz_buffer)
    this.onEnd = onEnd;
    this.ws = null;
    this.connected = false;
    this.pendingToolOutputs = new Map();
  }

  async connect(sessionConfig) {
    const url = `wss://api.openai.com/v1/realtime?model=${encodeURIComponent(CONFIG.realtimeModel)}`;

    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(url, {
        headers: {
          'Authorization': `Bearer ${CONFIG.openaiKey}`,
          'OpenAI-Beta': 'realtime=v1',
        },
      });

      this.ws.on('open', () => {
        console.log(`[OpenAI:${this.callId}] Connected to Realtime API`);
        this.connected = true;

        // Send session configuration
        this.ws.send(JSON.stringify({
          type: 'session.update',
          session: {
            instructions: sessionConfig.instructions,
            tools: sessionConfig.tools,
            tool_choice: 'auto',
            input_audio_format: 'pcm16',
            output_audio_format: 'pcm16',
            input_audio_transcription: { model: 'whisper-1' },
            turn_detection: { type: 'server_vad' },
            voice: sessionConfig.audio?.output?.voice || CONFIG.defaultVoice,
          },
        }));

        resolve();
      });

      this.ws.on('message', (data) => {
        this._handleMessage(JSON.parse(data.toString()));
      });

      this.ws.on('close', () => {
        console.log(`[OpenAI:${this.callId}] Connection closed`);
        this.connected = false;
        this.onEnd?.();
      });

      this.ws.on('error', (err) => {
        console.error(`[OpenAI:${this.callId}] Error:`, err.message);
        reject(err);
      });
    });
  }

  sendAudio(pcm16_24khz_base64) {
    if (!this.connected || !this.ws) return;
    this.ws.send(JSON.stringify({
      type: 'input_audio_buffer.append',
      audio: pcm16_24khz_base64,
    }));
  }

  close() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
  }

  async _handleMessage(msg) {
    switch (msg.type) {
      case 'response.audio.delta':
        // Decode base64 PCM16 24kHz audio and send to PBX
        if (msg.delta) {
          const audioBuf = Buffer.from(msg.delta, 'base64');
          this.onAudio?.(audioBuf);
        }
        break;

      case 'response.function_call_arguments.done': {
        const { call_id, name, arguments: argsStr } = msg;
        let args = {};
        try { args = JSON.parse(argsStr); } catch {}
        const result = await handleToolCall(name, args);

        // Send tool result back to OpenAI
        this.ws.send(JSON.stringify({
          type: 'conversation.item.create',
          item: {
            type: 'function_call_output',
            call_id,
            output: result,
          },
        }));
        // Trigger response after tool output
        this.ws.send(JSON.stringify({ type: 'response.create' }));
        break;
      }

      case 'response.done':
        console.log(`[OpenAI:${this.callId}] Response complete`);
        break;

      case 'error':
        console.error(`[OpenAI:${this.callId}] API error:`, msg.error);
        break;

      case 'input_audio_buffer.speech_started':
        console.log(`[OpenAI:${this.callId}] Speech detected`);
        break;

      case 'input_audio_buffer.speech_stopped':
        console.log(`[OpenAI:${this.callId}] Speech ended`);
        break;
    }
  }
}

// ── WebSocket Server (FreeSWITCH mod_audio_fork) ────────────────────────────

function startWebSocketServer() {
  const wss = new WebSocket.Server({ port: CONFIG.wsPort });
  console.log(`[Bridge] WebSocket server listening on port ${CONFIG.wsPort} (FreeSWITCH)`);

  wss.on('connection', async (pbxWs, req) => {
    const callId = `fs-${Date.now()}`;
    console.log(`[Bridge:${callId}] FreeSWITCH connection from ${req.socket.remoteAddress}`);

    let openai = null;

    try {
      const sessionConfig = await getSessionConfig(CONFIG.restaurantName, CONFIG.defaultVoice);
      if (!sessionConfig) {
        console.error(`[Bridge:${callId}] Cannot get session config, closing`);
        pbxWs.close();
        return;
      }

      openai = new OpenAIRealtimeSession(
        callId,
        // onAudio: downsample 24→8 kHz and send back to FreeSWITCH
        (pcm24) => {
          if (pbxWs.readyState === WebSocket.OPEN) {
            const pcm8 = downsample24to8(pcm24);
            pbxWs.send(pcm8);
          }
        },
        // onEnd
        () => {
          if (pbxWs.readyState === WebSocket.OPEN) pbxWs.close();
        },
      );

      await openai.connect(sessionConfig);
    } catch (err) {
      console.error(`[Bridge:${callId}] Failed to connect to OpenAI:`, err.message);
      pbxWs.close();
      return;
    }

    pbxWs.on('message', (data, isBinary) => {
      if (isBinary && openai?.connected) {
        // Binary = raw PCM16 8kHz mono audio from FreeSWITCH
        const pcm24 = upsample8to24(data);
        const b64 = pcm24.toString('base64');
        openai.sendAudio(b64);
      } else if (!isBinary) {
        // Text = JSON metadata from mod_audio_fork (channel info)
        try {
          const meta = JSON.parse(data.toString());
          console.log(`[Bridge:${callId}] Metadata:`, JSON.stringify(meta));
        } catch {
          // Ignore non-JSON text
        }
      }
    });

    pbxWs.on('close', () => {
      console.log(`[Bridge:${callId}] FreeSWITCH disconnected`);
      openai?.close();
    });

    pbxWs.on('error', (err) => {
      console.error(`[Bridge:${callId}] WS error:`, err.message);
      openai?.close();
    });
  });

  return wss;
}

// ── TCP Server (Asterisk AudioSocket) ───────────────────────────────────────
//
// AudioSocket protocol frames:
//   Type (1 byte) | Length (2 bytes, big-endian) | Payload (Length bytes)
//   0x00 = Hangup, 0x01 = Audio (slin 8kHz mono), 0x10 = UUID, 0x11 = Silence

function startTcpServer() {
  const server = net.createServer(async (socket) => {
    const callId = `ast-${Date.now()}`;
    console.log(`[Bridge:${callId}] Asterisk AudioSocket connection from ${socket.remoteAddress}`);

    let openai = null;
    let channelUuid = '';
    let recvBuf = Buffer.alloc(0);

    try {
      const sessionConfig = await getSessionConfig(CONFIG.restaurantName, CONFIG.defaultVoice);
      if (!sessionConfig) {
        console.error(`[Bridge:${callId}] Cannot get session config, closing`);
        socket.destroy();
        return;
      }

      openai = new OpenAIRealtimeSession(
        callId,
        // onAudio: downsample 24→8 kHz and send back via AudioSocket frames
        (pcm24) => {
          if (socket.writable) {
            const pcm8 = downsample24to8(pcm24);
            // Build AudioSocket frame: type=0x01, length, payload
            const frame = Buffer.alloc(3 + pcm8.length);
            frame.writeUInt8(0x01, 0);
            frame.writeUInt16BE(pcm8.length, 1);
            pcm8.copy(frame, 3);
            socket.write(frame);
          }
        },
        // onEnd
        () => {
          if (socket.writable) {
            // Send hangup frame
            const hangup = Buffer.from([0x00, 0x00, 0x00]);
            socket.write(hangup);
            socket.end();
          }
        },
      );

      await openai.connect(sessionConfig);
    } catch (err) {
      console.error(`[Bridge:${callId}] Failed to connect to OpenAI:`, err.message);
      socket.destroy();
      return;
    }

    socket.on('data', (chunk) => {
      recvBuf = Buffer.concat([recvBuf, chunk]);

      // Parse AudioSocket frames
      while (recvBuf.length >= 3) {
        const type = recvBuf.readUInt8(0);
        const len = recvBuf.readUInt16BE(1);

        if (recvBuf.length < 3 + len) break; // wait for more data

        const payload = recvBuf.subarray(3, 3 + len);
        recvBuf = recvBuf.subarray(3 + len);

        switch (type) {
          case 0x10: // UUID
            channelUuid = payload.toString('utf8');
            console.log(`[Bridge:${callId}] Asterisk channel UUID: ${channelUuid}`);
            break;

          case 0x01: // Audio data (slin 8kHz mono)
            if (openai?.connected) {
              const pcm24 = upsample8to24(payload);
              openai.sendAudio(pcm24.toString('base64'));
            }
            break;

          case 0x11: // Silence — ignore
            break;

          case 0x00: // Hangup
            console.log(`[Bridge:${callId}] Asterisk hangup`);
            openai?.close();
            socket.end();
            return;
        }
      }
    });

    socket.on('close', () => {
      console.log(`[Bridge:${callId}] Asterisk disconnected`);
      openai?.close();
    });

    socket.on('error', (err) => {
      console.error(`[Bridge:${callId}] TCP error:`, err.message);
      openai?.close();
    });
  });

  server.listen(CONFIG.tcpPort, () => {
    console.log(`[Bridge] TCP server listening on port ${CONFIG.tcpPort} (Asterisk AudioSocket)`);
  });

  return server;
}

// ── Main ────────────────────────────────────────────────────────────────────

function main() {
  if (!CONFIG.openaiKey) {
    console.error('[Bridge] FATAL: OPENAI_API_KEY is required');
    process.exit(1);
  }

  console.log('[Bridge] GastroVoice Media Bridge starting...');
  console.log(`[Bridge] Backend API: ${CONFIG.apiUrl}`);
  console.log(`[Bridge] Realtime model: ${CONFIG.realtimeModel}`);
  console.log(`[Bridge] Default voice: ${CONFIG.defaultVoice}`);
  console.log(`[Bridge] Restaurant: ${CONFIG.restaurantName} (${CONFIG.restaurantId})`);

  const wss = startWebSocketServer();
  const tcp = startTcpServer();

  // Graceful shutdown
  process.on('SIGTERM', () => {
    console.log('[Bridge] Shutting down...');
    wss.close();
    tcp.close();
    process.exit(0);
  });

  process.on('SIGINT', () => {
    console.log('[Bridge] Interrupted, shutting down...');
    wss.close();
    tcp.close();
    process.exit(0);
  });
}

main();
