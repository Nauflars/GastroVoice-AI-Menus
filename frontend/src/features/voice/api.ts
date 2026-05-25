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
