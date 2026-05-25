export interface ConversationTurn {
  role: 'user' | 'assistant';
  content: string;
}

export interface VoiceSimulateResponse {
  sessionId: string;
  reply: string;
  intent: string;
}
