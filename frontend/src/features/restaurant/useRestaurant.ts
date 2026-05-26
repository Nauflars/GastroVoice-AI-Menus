import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getRestaurant, updateRestaurant } from './api'
import type { UpdateRestaurantPayload } from './types'

export const RESTAURANT_QUERY_KEY = ['restaurant'] as const

export function useRestaurant() {
  return useQuery({
    queryKey: RESTAURANT_QUERY_KEY,
    queryFn: getRestaurant,
  })
}

export function useUpdateRestaurant() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateRestaurantPayload) => updateRestaurant(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: RESTAURANT_QUERY_KEY })
    },
  })
}
