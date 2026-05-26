import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getOrders, updateOrderStatus } from './api'
import type { OrderStatus } from './types'

export const ORDERS_QUERY_KEY = ['orders'] as const

export function useOrders(status?: OrderStatus) {
  return useQuery({
    queryKey: [...ORDERS_QUERY_KEY, status],
    queryFn: () => getOrders(status),
    refetchInterval: 30_000, // auto-refresh every 30s for live phone orders
  })
}

export function useUpdateOrderStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ orderId, status }: { orderId: string; status: OrderStatus }) =>
      updateOrderStatus(orderId, status),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ORDERS_QUERY_KEY })
    },
  })
}
