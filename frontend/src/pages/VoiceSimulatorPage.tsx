import { useRestaurant } from '@/features/restaurant/useRestaurant';
import VoiceSimulator from '@/features/voice/components/VoiceSimulator';

export default function VoiceSimulatorPage() {
  const query = useRestaurant();
  const restaurantId = query.data?.id ?? '';

  return (
    <div className="flex flex-col h-full">
      <div className="p-6 border-b">
        <h1 className="text-2xl font-bold">Voice Assistant Simulator</h1>
        <p className="text-gray-500 mt-1 text-sm">
          Simulate phone orders and reservations. In production, Asterisk calls{' '}
          <code className="bg-gray-100 px-1 rounded">POST /api/voice/call</code> with audio.
        </p>
      </div>
      <div className="flex-1 overflow-hidden">
        <VoiceSimulator restaurantId={restaurantId} />
      </div>
    </div>
  );
}
