import React, { useState, useRef, useEffect } from 'react';
import { simulateVoiceTurn } from '../api';
import type { ConversationTurn } from '../types';

interface VoiceSimulatorProps {
  restaurantId: string;
}

const INTENT_COLORS: Record<string, string> = {
  create_reservation: 'bg-blue-100 text-blue-800',
  check_availability: 'bg-yellow-100 text-yellow-800',
  create_order: 'bg-green-100 text-green-800',
  query_menu: 'bg-purple-100 text-purple-800',
  unknown: 'bg-gray-100 text-gray-800',
};

export default function VoiceSimulator({ restaurantId }: VoiceSimulatorProps) {
  const [history, setHistory] = useState<ConversationTurn[]>([]);
  const [input, setInput] = useState('');
  const [sessionId, setSessionId] = useState<string | undefined>();
  const [lastIntent, setLastIntent] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [history]);

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
  };

  return (
    <div className="flex flex-col h-full max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b">
        <div>
          <h2 className="text-lg font-semibold">Voice Simulator</h2>
          {sessionId && <p className="text-xs text-gray-400">Session: {sessionId.slice(0, 8)}…</p>}
        </div>
        <div className="flex items-center gap-2">
          {lastIntent && (
            <span className={`text-xs px-2 py-1 rounded-full font-medium ${INTENT_COLORS[lastIntent] ?? INTENT_COLORS.unknown}`}>
              {lastIntent.replace(/_/g, ' ')}
            </span>
          )}
          <button onClick={reset} className="text-sm text-gray-500 hover:text-gray-700 underline">
            Reset
          </button>
        </div>
      </div>

      {/* Conversation */}
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {history.length === 0 && (
          <p className="text-center text-gray-400 text-sm mt-8">
            Type a message to simulate a phone call with the AI voice assistant.
          </p>
        )}
        {history.map((turn, i) => (
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
        {loading && (
          <div className="flex justify-start">
            <div className="bg-gray-100 rounded-2xl px-4 py-2 text-sm text-gray-400 rounded-bl-none">
              Thinking…
            </div>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      {/* Input */}
      <div className="p-4 border-t">
        <div className="flex gap-2">
          <input
            type="text"
            value={input}
            onChange={e => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Type what the customer would say…"
            disabled={loading}
            className="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          />
          <button
            onClick={sendMessage}
            disabled={loading || !input.trim()}
            className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Send
          </button>
        </div>
      </div>
    </div>
  );
}
