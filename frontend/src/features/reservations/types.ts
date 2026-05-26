export interface Reservation {
  id: string
  date: string       // 'YYYY-MM-DD'
  timeSlot: string   // 'HH:MM'
  numPeople: number
  customerName: string
  customerPhone: string | null
  customerEmail: string | null
  notes: string | null
  status: 'confirmed' | 'cancelled' | 'pending'
  createdAt: string
}
