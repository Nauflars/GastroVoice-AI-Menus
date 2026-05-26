export type OrderStatus = 'pending' | 'confirmed' | 'preparing' | 'ready' | 'delivered' | 'cancelled'

export interface OrderLine {
  id: string
  menuItemId: string
  menuItemName: string
  quantity: number
  unitPrice: number
  currency: string
}

export interface Order {
  id: string
  status: OrderStatus
  tableNumber: string | null
  customerPhone: string | null
  lines: OrderLine[]
  total: number
  currency: string
  notes: string | null
  createdAt: string
  updatedAt: string
  source: 'phone' | 'web' | 'manual'
}
