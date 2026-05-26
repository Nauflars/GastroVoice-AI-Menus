import { useQuery } from '@tanstack/react-query'
import { getFullMenu } from './api'

export const MENU_QUERY_KEY = ['menu'] as const

export function useMenu() {
  return useQuery({
    queryKey: MENU_QUERY_KEY,
    queryFn: getFullMenu,
  })
}
