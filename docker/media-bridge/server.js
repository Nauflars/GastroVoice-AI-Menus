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
  realtimeModel: process.env.OPENAI_REALTIME_MODEL || 'gpt-realtime-2',
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

/**
 * AudioSocket frame pacer — buffers PCM and sends regular 20ms frames.
 * Asterisk expects consistent 320-byte slin8000 frames (20ms).
 */
class AudioFramePacer {
  constructor(socket, callId) {
    this.socket = socket;
    this.callId = callId;
    this.buffer = Buffer.alloc(0);
    this.FRAME_SIZE = 320; // 20ms of PCM16 at 8kHz mono
    this.timer = null;
    this.lastRealAudioTime = 0;
  }

  /** Add raw PCM8 data to the buffer. */
  push(pcm8) {
    this.lastRealAudioTime = Date.now();
    this.buffer = Buffer.concat([this.buffer, pcm8]);
    // Drain full frames immediately
    this._drain();
  }

  /** Send all complete 320-byte frames. */
  _drain() {
    while (this.buffer.length >= this.FRAME_SIZE && this.socket.writable) {
      const chunk = this.buffer.subarray(0, this.FRAME_SIZE);
      this.buffer = this.buffer.subarray(this.FRAME_SIZE);
      this._sendFrame(chunk);
    }
  }

  /** Flush remaining partial buffer (pad with silence). */
  flush() {
    if (this.buffer.length > 0 && this.socket.writable) {
      const padded = Buffer.alloc(this.FRAME_SIZE, 0);
      this.buffer.copy(padded);
      this.buffer = Buffer.alloc(0);
      this._sendFrame(padded);
    }
  }

  /** Send one AudioSocket frame (type=0x10). */
  _sendFrame(pcm) {
    const frame = Buffer.alloc(3 + pcm.length);
    frame.writeUInt8(0x10, 0);
    frame.writeUInt16BE(pcm.length, 1);
    pcm.copy(frame, 3);
    this.socket.write(frame);
  }

  /** Start keepalive — sends silence only when no real audio recent. */
  startKeepAlive() {
    if (this.timer) return;
    this.timer = setInterval(() => {
      if (this.socket.writable && (Date.now() - this.lastRealAudioTime > 300)) {
        this._sendFrame(Buffer.alloc(this.FRAME_SIZE, 0));
      }
    }, 200);
  }

  stopKeepAlive() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }
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

// Create an OpenAI Realtime session via REST API (same approach as web frontend)
// Returns { clientSecret, sessionConfig } with ephemeral token
async function createRealtimeSession(sessionConfig) {
  return new Promise((resolve, reject) => {
    const payload = JSON.stringify({ session: sessionConfig });
    const req = https.request('https://api.openai.com/v1/realtime/client_secrets', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${CONFIG.openaiKey}`,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload),
      },
    }, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          if (res.statusCode !== 200) {
            reject(new Error(`OpenAI session creation failed (${res.statusCode}): ${data}`));
            return;
          }
          resolve(parsed);
        } catch (e) {
          reject(new Error(`Failed to parse OpenAI response: ${data}`));
        }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

// ── Tool Call Handler ───────────────────────────────────────────────────────

async function handleToolCall(name, args) {
  console.log(`[Bridge] Tool call: ${name}`, JSON.stringify(args));

  try {
    switch (name) {
      case 'get_restaurant_info': {
        const info = await backendRequest('GET',
          `/api/restaurant/${encodeURIComponent(CONFIG.restaurantId)}`
        );
        return JSON.stringify(info);
      }
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
  constructor(callId, onAudio, onAudioDone, onEnd) {
    this.callId = callId;
    this.onAudio = onAudio; // callback(pcm16_24khz_buffer)
    this.onAudioDone = onAudioDone; // callback() - flush audio buffer
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
        },
      });

      this.ws.on('open', () => {
        console.log(`[OpenAI:${this.callId}] Connected to Realtime API (API key)`);
        this.connected = true;

        // Configure session with tools, instructions, VAD
        this.ws.send(JSON.stringify({
          type: 'session.update',
          session: {
            type: 'realtime',
            instructions: sessionConfig.instructions,
            tools: sessionConfig.tools,
            tool_choice: sessionConfig.tool_choice || 'auto',
            audio: {
              input: {
                transcription: { model: 'whisper-1' },
                turn_detection: { type: 'server_vad' },
              },
            },
          },
        }));

        console.log(`[OpenAI:${this.callId}] Sent session.update with ${sessionConfig.tools?.length} tools`);

        // Trigger an immediate greeting so the caller hears the AI right away
        this.ws.send(JSON.stringify({
          type: 'response.create',
          response: {
            instructions: 'Saluda brevemente al cliente. Di algo como: "¡Hola! Bienvenido a ' +
              (sessionConfig.restaurantName || 'nuestro restaurante') +
              '. ¿En qué puedo ayudarte?" Sé natural y cálido.',
          },
        }));
        console.log(`[OpenAI:${this.callId}] Triggered initial greeting`);

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
      case 'session.created':
        console.log(`[OpenAI:${this.callId}] Session created:`, JSON.stringify(msg.session, null, 2));
        break;

      case 'session.updated':
        console.log(`[OpenAI:${this.callId}] Session updated:`, JSON.stringify(msg.session, null, 2));
        break;

      // GA API audio events (response.output_audio.*)
      case 'response.output_audio.delta':
        {
          // Log first delta to check field names
          if (!this._loggedDelta) {
            this._loggedDelta = true;
            const keys = Object.keys(msg);
            console.log(`[OpenAI:${this.callId}] First audio delta keys: ${keys.join(', ')}, delta=${!!msg.delta}, audio=${!!msg.audio}, data=${!!msg.data}`);
          }
          const audioB64 = msg.delta || msg.audio || msg.data;
          if (audioB64) {
            const audioBuf = Buffer.from(audioB64, 'base64');
            this.onAudio?.(audioBuf);
          }
        }
        break;

      case 'response.output_audio.done':
        console.log(`[OpenAI:${this.callId}] Audio response done`);
        this.onAudioDone?.();
        break;

      case 'response.output_audio_transcript.delta':
        break; // streaming transcript, ignore

      case 'response.output_audio_transcript.done':
        console.log(`[OpenAI:${this.callId}] Transcript: ${msg.transcript}`);
        break;

      case 'response.text.delta':
      case 'response.text.done':
        break;

      case 'response.content_part.added':
      case 'response.content_part.done':
        break;

      case 'response.output_item.added':
      case 'response.output_item.done':
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
        console.log(`[OpenAI:${this.callId}] Response complete: status=${msg.response?.status}, output_count=${msg.response?.output?.length}`);
        if (msg.response?.output) {
          msg.response.output.forEach((item, i) => {
            console.log(`[OpenAI:${this.callId}]   output[${i}]: type=${item.type}, role=${item.role}, status=${item.status}, content_count=${item.content?.length}`);
            item.content?.forEach((c, j) => {
              console.log(`[OpenAI:${this.callId}]     content[${j}]: type=${c.type}, text=${c.text?.substring(0, 100) || '(none)'}`);
            });
          });
        }
        if (msg.response?.status === 'failed') {
          console.error(`[OpenAI:${this.callId}] Response FAILED:`, JSON.stringify(msg.response?.status_details));
        }
        break;

      case 'response.created':
        console.log(`[OpenAI:${this.callId}] Response created: id=${msg.response?.id}`);
        break;

      case 'response.output_item.added':
        console.log(`[OpenAI:${this.callId}] Output item added: type=${msg.item?.type}`);
        break;

      case 'error':
        console.error(`[OpenAI:${this.callId}] API error:`, JSON.stringify(msg.error));
        break;

      case 'input_audio_buffer.speech_started':
        console.log(`[OpenAI:${this.callId}] Speech detected`);
        break;

      case 'input_audio_buffer.speech_stopped':
        console.log(`[OpenAI:${this.callId}] Speech ended`);
        break;

      case 'input_audio_buffer.committed':
        break;

      case 'conversation.item.created':
      case 'conversation.item.added':
      case 'conversation.item.done':
      case 'conversation.item.input_audio_transcription.completed':
      case 'conversation.item.input_audio_transcription.delta':
        break;

      case 'rate_limits.updated':
        break;

      default:
        console.log(`[OpenAI:${this.callId}] Unhandled event: ${msg.type}`);
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

      sessionConfig.restaurantName = CONFIG.restaurantName;

      openai = new OpenAIRealtimeSession(
        callId,
        // onAudio: downsample 24→8 kHz and send back to FreeSWITCH
        (pcm24) => {
          if (pbxWs.readyState === WebSocket.OPEN) {
            const pcm8 = downsample24to8(pcm24);
            pbxWs.send(pcm8);
          }
        },
        // onAudioDone
        () => {},
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
// AudioSocket protocol frames (Asterisk 22+):
//   Type (1 byte) | Length (2 bytes, big-endian) | Payload (Length bytes)
//   0x00 = Hangup, 0x01 = UUID, 0x02 = Silence, 0x10 = Audio (slin 8kHz mono)

function startTcpServer() {
  const server = net.createServer((socket) => {
    const callId = `ast-${Date.now()}`;
    console.log(`[Bridge:${callId}] Asterisk AudioSocket connection from ${socket.remoteAddress}`);

    let openai = null;
    let channelUuid = '';
    let recvBuf = Buffer.alloc(0);
    let ready = false;
    let uuidReceived = false;

    const pacer = new AudioFramePacer(socket, callId);

    // Register data handler IMMEDIATELY to prevent buffering issues
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
          case 0x01: // UUID (Asterisk 22: type 0x01)
            if (payload.length === 16) {
              const hex = payload.toString('hex');
              channelUuid = `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20,32)}`;
            } else {
              channelUuid = payload.toString('utf8').replace(/\0/g, '').trim();
            }
            console.log(`[Bridge:${callId}] UUID received (${payload.length} bytes): ${channelUuid}`);
            if (!uuidReceived) {
              uuidReceived = true;
              pacer.startKeepAlive();
              console.log(`[Bridge:${callId}] Starting keepalive frames`);
            }
            break;

          case 0x10: // Audio data (Asterisk 22: type 0x10, slin 8kHz mono)
            if (ready && openai?.connected) {
              const pcm24 = upsample8to24(payload);
              openai.sendAudio(pcm24.toString('base64'));
            }
            break;

          case 0x02: // Silence — ignore
            break;

          case 0x00: // Hangup
            console.log(`[Bridge:${callId}] Asterisk hangup`);
            pacer.stopKeepAlive();
            openai?.close();
            socket.end();
            return;

          default:
            console.log(`[Bridge:${callId}] Unknown frame type=0x${type.toString(16)} len=${len}`);
        }
      }
    });

    socket.on('close', () => {
      console.log(`[Bridge:${callId}] Asterisk disconnected`);
      pacer.stopKeepAlive();
      openai?.close();
    });

    socket.on('error', (err) => {
      console.error(`[Bridge:${callId}] TCP error:`, err.message);
      pacer.stopKeepAlive();
      openai?.close();
    });

    // Setup OpenAI session asynchronously (don't block the connection)
    (async () => {
      try {
        // Get session config from backend (instructions, tools, voice)
        const sessionConfig = await getSessionConfig(CONFIG.restaurantName, CONFIG.defaultVoice);
        if (!sessionConfig) {
          console.error(`[Bridge:${callId}] Cannot get session config, closing`);
          pacer.stopKeepAlive();
          socket.destroy();
          return;
        }

        console.log(`[Bridge:${callId}] Session config received: voice=${sessionConfig.audio?.output?.voice}`);
        // Attach restaurant name for greeting
        sessionConfig.restaurantName = CONFIG.restaurantName;

        openai = new OpenAIRealtimeSession(
          callId,
          // onAudio: downsample 24→8 kHz, buffer into 20ms frames, send to Asterisk
          (pcm24) => {
            const pcm8 = downsample24to8(pcm24);
            pacer.push(pcm8);
          },
          // onAudioDone: flush any remaining buffered audio
          () => {
            pacer.flush();
          },
          // onEnd
          () => {
            if (socket.writable) {
              const hangup = Buffer.from([0x00, 0x00, 0x00]);
              socket.write(hangup);
              socket.end();
            }
          },
        );

        // Connect WebSocket with API key (server-to-server)
        await openai.connect(sessionConfig);
        ready = true;
        console.log(`[Bridge:${callId}] Ready — forwarding audio (keepalive continues)`);
      } catch (err) {
        console.error(`[Bridge:${callId}] Failed to connect to OpenAI:`, err.message);
        pacer.stopKeepAlive();
        socket.destroy();
      }
    })();
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
