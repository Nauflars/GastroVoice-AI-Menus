import apiClient from '@/lib/apiClient'
import type { Order, OrderStatus } from './types'

export async function getOrders(status?: OrderStatus): Promise<Order[]> {
  const params = status != null ? { status } : {}
  const res = await apiClient.get<Order[]>('/api/orders', { params })
  return res.data
}

export async function updateOrderStatus(orderId: string, status: OrderStatus): Promise<void> {
  await apiClient.patch(`/api/orders/${orderId}`, { status })
}
