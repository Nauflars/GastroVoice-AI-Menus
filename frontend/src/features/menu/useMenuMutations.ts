import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  createCategory,
  updateCategory,
  deactivateCategory,
  createMenuItem,
  toggleMenuItemAvailability,
} from './api'
import { MENU_QUERY_KEY } from './useMenu'

export function useCreateCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; displayOrder: number }) => createCategory(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: MENU_QUERY_KEY }),
  })
}

export function useUpdateCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: { id: string; name: string; displayOrder: number }) =>
      updateCategory(id, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: MENU_QUERY_KEY }),
  })
}

export function useDeactivateCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deactivateCategory(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: MENU_QUERY_KEY }),
  })
}

export function useCreateMenuItem() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      categoryId: string
      name: string
      description: string | null
      price: number
      currency?: string
    }) => createMenuItem(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: MENU_QUERY_KEY }),
  })
}

export function useToggleMenuItemAvailability() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => toggleMenuItemAvailability(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: MENU_QUERY_KEY }),
  })
}
