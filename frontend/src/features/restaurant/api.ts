import apiClient from '@/lib/apiClient'
import type { Restaurant, UpdateRestaurantPayload } from './types'

export async function getRestaurant(): Promise<Restaurant> {
  const res = await apiClient.get<Restaurant>('/api/restaurant')
  return res.data
}

export async function updateRestaurant(payload: UpdateRestaurantPayload): Promise<void> {
  await apiClient.put('/api/restaurant', payload)
}
