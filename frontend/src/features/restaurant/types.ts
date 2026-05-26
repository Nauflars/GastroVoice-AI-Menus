export interface OpeningHour {
  id?: string
  dayOfWeek: number // 0=Mon, 6=Sun
  openTime: string | null
  closeTime: string | null
  isClosed: boolean
}

export interface Restaurant {
  id: string
  name: string
  address: string
  phone: string
  seatCapacity: number
  slotDurationMinutes: number
  timezone: string
  openingHours: OpeningHour[]
  createdAt: string
  updatedAt: string
}

export type UpdateRestaurantPayload = Omit<Restaurant, 'id' | 'createdAt' | 'updatedAt'>
