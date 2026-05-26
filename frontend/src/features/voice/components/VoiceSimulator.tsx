import React, { useState, useRef, useEffect } from 'react';
import { simulateVoiceTurn } from '../api';
import type { ConversationTurn } from '../types';
import { useRealtimeSession } from '../hooks/useRealtimeSession';

const API_URL = import.meta.env.VITE_API_URL ?? '';

type Mode = 'text' | 'audio' | 'realtime';

interface VoiceSimulatorProps {
  restaurantId: string;
  restaurantName?: string;
}

const INTENT_COLORS: Record<string, string> = {
  create_reservation: 'bg-blue-100 text-blue-800',
  check_availability: 'bg-yellow-100 text-yellow-800',
  create_order: 'bg-green-100 text-green-800',
  query_menu: 'bg-purple-100 text-purple-800',
  unknown: 'bg-gray-100 text-gray-800',
};

export default function VoiceSimulator({ restaurantId, restaurantName }: VoiceSimulatorProps) {
  const [history, setHistory] = useState<ConversationTurn[]>([]);
  const [input, setInput] = useState('');
  const [sessionId, setSessionId] = useState<string | undefined>();
  const [lastIntent, setLastIntent] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [recording, setRecording] = useState(false);
  const [mode, setMode] = useState<Mode>('text');
  const bottomRef = useRef<HTMLDivElement>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);

  const realtime = useRealtimeSession();

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [history, realtime.messages]);

  // Sync realtime messages to display on mode=realtime
  const displayHistory = mode === 'realtime' ? realtime.messages : history;

  const sendMessage = async () => {
    const text = input.trim();
    if (!text || loading) return;

    const userTurn: ConversationTurn = { role: 'user', content: text };
    setHistory(prev => [...prev, userTurn]);
    setInput('');
    setLoading(true);

    try {
      const result = await simulateVoiceTurn(restaurantId, text, sessionId);
      setSessionId(result.sessionId);
      setLastIntent(result.intent);
      setHistory(prev => [...prev, { role: 'assistant', content: result.reply }]);
    } catch {
      setHistory(prev => [...prev, { role: 'assistant', content: 'Error connecting to voice service.' }]);
    } finally {
      setLoading(false);
    }
  };

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/ogg';
      const mediaRecorder = new MediaRecorder(stream, { mimeType });
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (e) => {
        if (e.data.size > 0) audioChunksRef.current.push(e.data);
      };

      mediaRecorder.onstop = async () => {
        stream.getTracks().forEach(t => t.stop());
        const audioBlob = new Blob(audioChunksRef.current, { type: mimeType });
        await sendAudio(audioBlob);
      };

      mediaRecorder.start();
      setRecording(true);
    } catch {
      alert('No se puede acceder al micrófono. Comprueba los permisos del navegador.');
    }
  };

  const stopRecording = () => {
    mediaRecorderRef.current?.stop();
    setRecording(false);
  };

  const sendAudio = async (audioBlob: Blob) => {
    setLoading(true);
    setHistory(prev => [...prev, { role: 'user', content: '🎤 [Audio enviado…]' }]);

    try {
      const token = localStorage.getItem('token') ?? '';
      const form = new FormData();
      form.append('audioFile', audioBlob, 'recording.webm');
      form.append('restaurantId', restaurantId);
      if (sessionId) form.append('sessionId', sessionId);
      form.append('callerId', 'browser');

      const res = await fetch(`${API_URL}/api/voice/call`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: form,
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const newSessionId = res.headers.get('X-Session-Id');
      const intent = res.headers.get('X-Intent') ?? '';
      const transcript = res.headers.get('X-Transcript') ?? '';
      if (newSessionId) setSessionId(newSessionId);
      if (intent) setLastIntent(intent);

      if (transcript) {
        setHistory(prev => {
          const updated = [...prev];
          updated[updated.length - 1] = { role: 'user', content: `🎤 "${transcript}"` };
          return updated;
        });
      }

      const audioBuffer = await res.arrayBuffer();
      const audioCtx = new AudioContext();
      const decoded = await audioCtx.decodeAudioData(audioBuffer);
      const source = audioCtx.createBufferSource();
      source.buffer = decoded;
      source.connect(audioCtx.destination);
      source.start();

      setHistory(prev => [...prev, { role: 'assistant', content: '🔊 [Respuesta de audio reproducida]' }]);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Error desconocido';
      setHistory(prev => [...prev, { role: 'assistant', content: `Error: ${msg}` }]);
    } finally {
      setLoading(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const reset = () => {
    setHistory([]);
    setSessionId(undefined);
    setLastIntent('');
    if (realtime.status !== 'idle') realtime.stop();
  };

  const handleModeChange = (newMode: Mode) => {
    if (newMode === mode) return;
    if (mode === 'realtime' && realtime.status !== 'idle') realtime.stop();
    setMode(newMode);
  };

  const toggleRealtime = async () => {
    if (realtime.status === 'connected' || realtime.status === 'connecting') {
      realtime.stop();
    } else {
      await realtime.start(restaurantId, restaurantName);
    }
  };

  const modeLabels: Record<Mode, string> = {
    text: '⌨️ Texto',
    audio: '🎤 Voz',
    realtime: '⚡ Realtime',
  };

  return (
    <div className="flex flex-col h-full max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b">
        <div>
          <h2 className="text-lg font-semibold">Voice Simulator</h2>
          {sessionId && mode !== 'realtime' && <p className="text-xs text-gray-400">Session: {sessionId.slice(0, 8)}…</p>}
          {mode === 'realtime' && realtime.status === 'connected' && (
            <p className="text-xs text-green-500">● Realtime conectado</p>
          )}
        </div>
        <div className="flex items-center gap-2">
          {lastIntent && mode !== 'realtime' && (
            <span className={`text-xs px-2 py-1 rounded-full font-medium ${INTENT_COLORS[lastIntent] ?? INTENT_COLORS.unknown}`}>
              {lastIntent.replace(/_/g, ' ')}
            </span>
          )}
          {/* Mode selector */}
          <div className="flex rounded-lg border overflow-hidden text-xs font-medium">
            {(['text', 'audio', 'realtime'] as Mode[]).map(m => (
              <button
                key={m}
                onClick={() => handleModeChange(m)}
                className={`px-2 py-1 transition-colors ${mode === m ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
              >
                {modeLabels[m]}
              </button>
            ))}
          </div>
          <button onClick={reset} className="text-sm text-gray-500 hover:text-gray-700 underline">
            Reset
          </button>
        </div>
      </div>

      {/* Conversation */}
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {displayHistory.length === 0 && (
          <p className="text-center text-gray-400 text-sm mt-8">
            {mode === 'realtime'
              ? 'Conecta el modo Realtime y habla directamente con GPT-4o.'
              : mode === 'audio'
              ? 'Pulsa el micrófono y habla para simular una llamada telefónica real.'
              : 'Escribe un mensaje para simular una llamada con el asistente de voz.'}
          </p>
        )}
        {mode === 'realtime' && realtime.error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
            ⚠️ {realtime.error}
          </div>
        )}
        {displayHistory.map((turn, i) => (
          <div
            key={i}
            className={`flex ${turn.role === 'user' ? 'justify-end' : 'justify-start'}`}
          >
            <div
              className={`max-w-xs lg:max-w-md px-4 py-2 rounded-2xl text-sm ${
                turn.role === 'user'
                  ? 'bg-blue-600 text-white rounded-br-none'
                  : 'bg-gray-100 text-gray-800 rounded-bl-none'
              }`}
            >
              {turn.content}
            </div>
          </div>
        ))}
        {loading && mode !== 'realtime' && (
          <div className="flex justify-start">
            <div className="bg-gray-100 rounded-2xl px-4 py-2 text-sm text-gray-400 rounded-bl-none">
              {mode === 'audio' ? 'Procesando audio…' : 'Thinking…'}
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="p-4 border-t">
        {mode === 'realtime' ? (
          <div className="flex flex-col items-center gap-3">
            <button
              onClick={toggleRealtime}
              disabled={realtime.status === 'connecting'}
              className={`w-20 h-20 rounded-full text-3xl font-bold shadow-lg transition-all select-none disabled:opacity-40 ${
                realtime.status === 'connected'
                  ? 'bg-green-500 text-white scale-110 animate-pulse'
                  : realtime.status === 'connecting'
                  ? 'bg-yellow-400 text-white'
                  : 'bg-purple-600 text-white hover:bg-purple-700 active:scale-95'
              }`}
            >
              {realtime.status === 'connected' ? '⏹' : realtime.status === 'connecting' ? '⏳' : '⚡'}
            </button>
            <p className="text-xs text-gray-400">
              {realtime.status === 'connected'
                ? 'Hablando con GPT-4o en tiempo real — pulsa para detener'
                : realtime.status === 'connecting'
                ? 'Conectando…'
                : 'Pulsa para iniciar sesión Realtime con GPT-4o'}
            </p>
          </div>
        ) : mode === 'audio' ? (
          <div className="flex flex-col items-center gap-3">
            <button
              onMouseDown={startRecording}
              onMouseUp={stopRecording}
              onTouchStart={startRecording}
              onTouchEnd={stopRecording}
              disabled={loading}
              className={`w-20 h-20 rounded-full text-3xl font-bold shadow-lg transition-all select-none disabled:opacity-40 ${
                recording
                  ? 'bg-red-500 text-white scale-110 animate-pulse'
                  : 'bg-blue-600 text-white hover:bg-blue-700 active:scale-95'
              }`}
            >
              {recording ? '⏹' : '🎤'}
            </button>
            <p className="text-xs text-gray-400">
              {recording ? 'Suelta para enviar' : 'Mantén pulsado para hablar'}
            </p>
          </div>
        ) : (
          <div className="flex gap-2">
            <input
              type="text"
              value={input}
              onChange={e => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Escribe lo que diría el cliente…"
              disabled={loading}
              className="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            />
            <button
              onClick={sendMessage}
              disabled={loading || !input.trim()}
              className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Enviar
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
