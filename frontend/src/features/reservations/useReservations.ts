import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getReservations, cancelReservation } from './api'

export function useReservations(params?: { date?: string; status?: string }) {
  return useQuery({
    queryKey: ['reservations', params],
    queryFn: () => getReservations(params),
  })
}

export function useCancelReservation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: cancelReservation,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['reservations'] }),
  })
}
