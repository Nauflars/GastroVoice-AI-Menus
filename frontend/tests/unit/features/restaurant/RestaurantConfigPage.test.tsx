import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { vi, describe, it, expect, beforeEach } from 'vitest'
import RestaurantConfigPage from '@/features/restaurant/RestaurantConfigPage'
import * as api from '@/features/restaurant/api'

const mockRestaurant = {
  id: '0190e4a0-dead-beef-baad-f00d00000001',
  name: 'Test Restaurant',
  address: '1 Test St',
  phone: '+33100000000',
  seatCapacity: 50,
  slotDurationMinutes: 30,
  timezone: 'Europe/Paris',
  openingHours: [],
  createdAt: '2026-01-01T00:00:00Z',
  updatedAt: '2026-01-01T00:00:00Z',
}

vi.mock('@/features/restaurant/api', () => ({
  getRestaurant: vi.fn(),
  updateRestaurant: vi.fn(),
}))

function renderWithQuery(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>)
}

describe('RestaurantConfigPage', () => {
  beforeEach(() => {
    vi.mocked(api.getRestaurant).mockResolvedValue(mockRestaurant)
    vi.mocked(api.updateRestaurant).mockResolvedValue(undefined)
  })

  it('displays restaurant name and ID after loading', async () => {
    renderWithQuery(<RestaurantConfigPage />)

    await waitFor(() => {
      expect(screen.getByDisplayValue('Test Restaurant')).toBeInTheDocument()
    })

    expect(screen.getByText(/0190e4a0-dead-beef/)).toBeInTheDocument()
  })

  it('shows loading state initially', () => {
    vi.mocked(api.getRestaurant).mockImplementation(() => new Promise(() => {}))
    renderWithQuery(<RestaurantConfigPage />)
    expect(screen.getByText('Loading…')).toBeInTheDocument()
  })

  it('submits updated name', async () => {
    const user = userEvent.setup()
    renderWithQuery(<RestaurantConfigPage />)

    await waitFor(() => screen.getByDisplayValue('Test Restaurant'))

    const nameInput = screen.getByDisplayValue('Test Restaurant')
    await user.clear(nameInput)
    await user.type(nameInput, 'New Name')

    await user.click(screen.getByRole('button', { name: /save configuration/i }))

    await waitFor(() => {
      expect(api.updateRestaurant).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'New Name' }),
      )
    })
  })
})
