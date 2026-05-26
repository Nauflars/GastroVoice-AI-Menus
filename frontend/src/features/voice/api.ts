import apiClient from '@/lib/apiClient';
import type { VoiceSimulateResponse } from './types';

export async function simulateVoiceTurn(
  restaurantId: string,
  text: string,
  sessionId?: string,
): Promise<VoiceSimulateResponse> {
  const response = await apiClient.post('/api/voice/simulate', {
    restaurantId,
    text,
    sessionId,
  });
  return response.data;
}

export interface RealtimeSessionResponse {
  clientSecret: string;
  sessionId: string;
  restaurantId: string;
  restaurantName: string;
  systemPrompt: string;
  tools: object[];
}

export async function createRealtimeSession(
  restaurantId: string,
  restaurantName?: string,
): Promise<RealtimeSessionResponse> {
  const response = await apiClient.post('/api/voice/realtime-session', {
    restaurantId,
    restaurantName,
  });
  return response.data;
}
