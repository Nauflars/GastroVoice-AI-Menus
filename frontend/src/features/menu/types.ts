export interface MenuCategory {
  id: string
  name: string
  description: string | null
  sortOrder: number
}

export interface MenuItem {
  id: string
  categoryId: string
  name: string
  description: string | null
  price: number
  currency: string
  isAvailable: boolean
  allergens: string[]
  imageUrl: string | null
}

export interface MenuCategoryWithItems extends MenuCategory {
  items: MenuItem[]
}

export interface MenuImportPreview {
  previewId: string
  categories: Array<{
    name: string
    items: Array<{
      name: string
      description: string | null
      price: number
      currency: string
      allergens: string[]
    }>
  }>
}
