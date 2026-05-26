import apiClient from '@/lib/apiClient'
import type { Reservation } from './types'

export async function getReservations(params?: {
  date?: string
  status?: string
}): Promise<Reservation[]> {
  const res = await apiClient.get<Reservation[]>('/api/reservations', { params })
  return res.data
}

export async function cancelReservation(id: string): Promise<void> {
  await apiClient.post(`/api/reservations/${id}/cancel`)
}
