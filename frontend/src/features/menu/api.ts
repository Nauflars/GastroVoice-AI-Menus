import apiClient from '@/lib/apiClient'
import type { MenuCategory, MenuItem, MenuCategoryWithItems, MenuImportPreview } from './types'

export async function getFullMenu(): Promise<MenuCategoryWithItems[]> {
  const res = await apiClient.get<MenuCategoryWithItems[]>('/api/menu')
  return res.data
}

export async function createCategory(data: { name: string; displayOrder: number }): Promise<{ id: string }> {
  const res = await apiClient.post<{ id: string }>('/api/menu/categories', data)
  return res.data
}

export async function updateCategory(id: string, data: { name: string; displayOrder: number }): Promise<void> {
  await apiClient.put(`/api/menu/categories/${id}`, data)
}

export async function deactivateCategory(id: string): Promise<void> {
  await apiClient.delete(`/api/menu/categories/${id}`)
}

export async function createMenuItem(data: {
  categoryId: string
  name: string
  description: string | null
  price: number
  currency?: string
}): Promise<{ id: string }> {
  const res = await apiClient.post<{ id: string }>('/api/menu/items', data)
  return res.data
}

export async function toggleMenuItemAvailability(id: string): Promise<void> {
  await apiClient.patch(`/api/menu/items/${id}/toggle`)
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
