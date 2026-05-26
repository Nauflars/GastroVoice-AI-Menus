import { useRef, useState, useCallback } from 'react';
import { createRealtimeSession } from '../api';
import apiClient from '@/lib/apiClient';

const REALTIME_MODEL = 'gpt-4o-mini-realtime';

export interface RealtimeMessage {
  role: 'user' | 'assistant';
  content: string;
}

interface UseRealtimeSessionReturn {
  messages: RealtimeMessage[];
  status: 'idle' | 'connecting' | 'connected' | 'error';
  error: string | null;
  start: (restaurantId: string, restaurantName?: string, voice?: string) => Promise<void>;
  stop: () => void;
}

export function useRealtimeSession(): UseRealtimeSessionReturn {
  const [messages, setMessages] = useState<RealtimeMessage[]>([]);
  const [status, setStatus] = useState<'idle' | 'connecting' | 'connected' | 'error'>('idle');
  const [error, setError] = useState<string | null>(null);

  const pcRef = useRef<RTCPeerConnection | null>(null);
  const dcRef = useRef<RTCDataChannel | null>(null);
  const audioElRef = useRef<HTMLAudioElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const restaurantIdRef = useRef<string>('');

  // Pending function calls: callId → name
  const pendingCallsRef = useRef<Map<string, string>>(new Map());
  const pendingArgsRef = useRef<Map<string, string>>(new Map());

  const addMessage = useCallback((role: 'user' | 'assistant', content: string) => {
    setMessages(prev => [...prev, { role, content }]);
  }, []);

  const sendEvent = useCallback((event: object) => {
    if (dcRef.current?.readyState === 'open') {
      dcRef.current.send(JSON.stringify(event));
    }
  }, []);

  const handleFunctionCall = useCallback(async (callId: string, name: string, args: string) => {
    let result: object = { error: 'Unknown function' };

    try {
      const parsed = JSON.parse(args || '{}');

      if (name === 'query_menu') {
        const res = await apiClient.get(`/api/menu/${restaurantIdRef.current}`);
        result = { menu: res.data };

      } else if (name === 'check_availability') {
        const res = await apiClient.get('/api/reservations/availability', {
          params: {
            restaurantId: restaurantIdRef.current,
            date: parsed.date,
            timeSlot: parsed.timeSlot,
            numPeople: parsed.numPeople,
          },
        });
        result = res.data;

      } else if (name === 'create_reservation') {
        const res = await apiClient.post('/api/reservations', {
          restaurantId: restaurantIdRef.current,
          date: parsed.date,
          timeSlot: parsed.timeSlot,
          numPeople: parsed.numPeople,
          customerName: parsed.customerName,
          customerPhone: parsed.customerPhone ?? null,
          customerEmail: parsed.customerEmail ?? null,
          notes: parsed.notes ?? null,
        });
        result = res.data;

      } else if (name === 'create_order') {
        const res = await apiClient.post('/api/orders', {
          restaurantId: restaurantIdRef.current,
          items: parsed.items,
          customerName: parsed.customerName ?? 'Cliente',
        });
        result = res.data;
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Error';
      result = { error: msg };
    }

    // Return result to OpenAI
    sendEvent({
      type: 'conversation.item.create',
      item: {
        type: 'function_call_output',
        call_id: callId,
        output: JSON.stringify(result),
      },
    });

    // Trigger response
    sendEvent({ type: 'response.create' });
  }, [sendEvent]);

  const handleDataChannelMessage = useCallback((raw: string) => {
    let event: { type: string; [key: string]: unknown };
    try {
      event = JSON.parse(raw);
    } catch {
      return;
    }

    switch (event.type) {
      // User transcript (VAD-detected speech)
      case 'conversation.item.input_audio_transcription.completed': {
        const transcript = (event.transcript as string) ?? '';
        if (transcript.trim()) addMessage('user', `🎤 "${transcript.trim()}"`);
        break;
      }

      // Assistant audio transcript
      case 'response.audio_transcript.done': {
        const transcript = (event.transcript as string) ?? '';
        if (transcript.trim()) addMessage('assistant', transcript.trim());
        break;
      }

      // Function call accumulation
      case 'response.function_call_arguments.delta': {
        const callId = event.call_id as string;
        const delta = (event.delta as string) ?? '';
        pendingArgsRef.current.set(callId, (pendingArgsRef.current.get(callId) ?? '') + delta);
        break;
      }

      case 'response.function_call_arguments.done': {
        const callId = event.call_id as string;
        const name = pendingCallsRef.current.get(callId) ?? (event.name as string) ?? '';
        const args = pendingArgsRef.current.get(callId) ?? (event.arguments as string) ?? '{}';
        pendingCallsRef.current.delete(callId);
        pendingArgsRef.current.delete(callId);
        if (name) {
          addMessage('assistant', `⚙️ Ejecutando: ${name.replace(/_/g, ' ')}…`);
          void handleFunctionCall(callId, name, args);
        }
        break;
      }

      case 'response.output_item.added': {
        const item = event.item as { type?: string; call_id?: string; name?: string };
        if (item?.type === 'function_call' && item.call_id && item.name) {
          pendingCallsRef.current.set(item.call_id, item.name);
        }
        break;
      }

      case 'error': {
        const errMsg = (event.message as string) ?? 'Realtime error';
        setError(errMsg);
        break;
      }

      default:
        break;
    }
  }, [addMessage, handleFunctionCall]);

  const start = useCallback(async (restaurantId: string, restaurantName?: string, voice?: string) => {
    if (status === 'connected' || status === 'connecting') return;

    setStatus('connecting');
    setError(null);
    setMessages([]);
    restaurantIdRef.current = restaurantId;

    try {
      const session = await createRealtimeSession(restaurantId, restaurantName, voice);
      const { clientSecret } = session;

      const pc = new RTCPeerConnection();
      pcRef.current = pc;

      // Audio element for playback
      const audioEl = new Audio();
      audioEl.autoplay = true;
      audioElRef.current = audioEl;

      pc.ontrack = (e) => {
        audioEl.srcObject = e.streams[0];
      };

      // Data channel for events
      const dc = pc.createDataChannel('oai-events');
      dcRef.current = dc;

      dc.onopen = () => {
        setStatus('connected');
        // Reinforce session config via session.update after channel opens
        // Small timeout ensures the data channel is fully ready
        setTimeout(() => {
          sendEvent({
            type: 'session.update',
            session: {
              instructions: session.systemPrompt,
              tools: session.tools,
              tool_choice: 'auto',
              turn_detection: { type: 'server_vad' },
              input_audio_transcription: { model: 'whisper-1' },
            },
          });
        }, 200);
      };

      dc.onmessage = (e) => handleDataChannelMessage(e.data as string);

      dc.onclose = () => setStatus('idle');

      // Microphone
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      streamRef.current = stream;
      stream.getTracks().forEach(track => pc.addTrack(track, stream));

      // SDP offer
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);

      const sdpResponse = await fetch(
        'https://api.openai.com/v1/realtime/calls',
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${clientSecret}`,
            'Content-Type': 'application/sdp',
          },
          body: offer.sdp,
        }
      );

      if (!sdpResponse.ok) {
        throw new Error(`OpenAI SDP error: ${sdpResponse.status}`);
      }

      const answerSdp = await sdpResponse.text();
      await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });

    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Connection failed';
      setError(msg);
      setStatus('error');
    }
  }, [status, sendEvent, handleDataChannelMessage]);

  const stop = useCallback(() => {
    dcRef.current?.close();
    pcRef.current?.close();
    streamRef.current?.getTracks().forEach(t => t.stop());
    if (audioElRef.current) {
      audioElRef.current.srcObject = null;
    }
    pcRef.current = null;
    dcRef.current = null;
    streamRef.current = null;
    setStatus('idle');
    setMessages([]);
    setError(null);
  }, []);

  return { messages, status, error, start, stop };
}
