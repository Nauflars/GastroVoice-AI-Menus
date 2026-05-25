import apiClient from '@/lib/apiClient'
import type { MenuCategoryWithItems, MenuImportPreview } from './types'

export async function getFullMenu(): Promise<MenuCategoryWithItems[]> {
  const res = await apiClient.get<MenuCategoryWithItems[]>('/api/menu')
  return res.data
}

export async function uploadMenuImage(file: File): Promise<MenuImportPreview> {
  const form = new FormData()
  form.append('image', file)
  const res = await apiClient.post<MenuImportPreview>('/api/menu/import', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return res.data
}

export async function confirmMenuImport(previewId: string): Promise<void> {
  await apiClient.post(`/api/menu/import/${previewId}/confirm`)
}
