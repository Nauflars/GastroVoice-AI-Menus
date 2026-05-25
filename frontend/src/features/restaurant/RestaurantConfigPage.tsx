import { useEffect, useState } from 'react'
import { useRestaurant, useUpdateRestaurant } from './useRestaurant'
import type { OpeningHour } from './types'

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

const defaultOpeningHours = (): OpeningHour[] =>
  DAYS.map((_, i) => ({ dayOfWeek: i, isClosed: i >= 5, openTime: '09:00', closeTime: '22:00' }))

export default function RestaurantConfigPage() {
  const { data: restaurant, isLoading, isError } = useRestaurant()
  const updateMutation = useUpdateRestaurant()

  const [name, setName] = useState('')
  const [address, setAddress] = useState('')
  const [phone, setPhone] = useState('')
  const [seatCapacity, setSeatCapacity] = useState(50)
  const [slotDurationMinutes, setSlotDurationMinutes] = useState(30)
  const [timezone, setTimezone] = useState('UTC')
  const [openingHours, setOpeningHours] = useState<OpeningHour[]>(defaultOpeningHours())
  const [successMsg, setSuccessMsg] = useState<string | null>(null)

  useEffect(() => {
    if (restaurant != null) {
      setName(restaurant.name)
      setAddress(restaurant.address)
      setPhone(restaurant.phone)
      setSeatCapacity(restaurant.seatCapacity)
      setSlotDurationMinutes(restaurant.slotDurationMinutes)
      setTimezone(restaurant.timezone)
      if (restaurant.openingHours.length > 0) {
        setOpeningHours(restaurant.openingHours)
      }
    }
  }, [restaurant])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    updateMutation.mutate(
      { name, address, phone, seatCapacity, slotDurationMinutes, timezone, openingHours },
      {
        onSuccess: () => {
          setSuccessMsg('Restaurant configuration saved!')
          setTimeout(() => setSuccessMsg(null), 3000)
        },
      },
    )
  }

  const updateHour = (idx: number, field: keyof OpeningHour, value: string | boolean) => {
    setOpeningHours((prev) =>
      prev.map((h, i) => (i === idx ? { ...h, [field]: value } : h)),
    )
  }

  if (isLoading) return <p className="text-gray-500">Loading…</p>
  if (isError) return <p className="text-red-500">Failed to load restaurant configuration.</p>

  return (
    <div className="max-w-2xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Restaurant Configuration</h1>
        {restaurant != null && (
          <p className="text-xs text-gray-400 mt-1 font-mono">ID: {restaurant.id}</p>
        )}
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Basic info */}
        <section className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          <h2 className="font-semibold text-gray-800">Basic Information</h2>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Restaurant Name</label>
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <input
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              required
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              required
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </section>

        {/* Capacity & slots */}
        <section className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
          <h2 className="font-semibold text-gray-800">Capacity & Reservations</h2>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Seat Capacity</label>
              <input
                type="number"
                min={1}
                value={seatCapacity}
                onChange={(e) => setSeatCapacity(Number(e.target.value))}
                required
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Slot Duration (min)</label>
              <select
                value={slotDurationMinutes}
                onChange={(e) => setSlotDurationMinutes(Number(e.target.value))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {[15, 20, 30, 45, 60, 90, 120].map((v) => (
                  <option key={v} value={v}>{v} min</option>
                ))}
              </select>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <input
              value={timezone}
              onChange={(e) => setTimezone(e.target.value)}
              placeholder="e.g. Europe/Paris"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </section>

        {/* Opening hours */}
        <section className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="font-semibold text-gray-800 mb-4">Opening Hours</h2>
          <div className="space-y-3">
            {openingHours.map((hour, idx) => (
              <div key={hour.dayOfWeek} className="flex items-center gap-3">
                <span className="w-24 text-sm text-gray-700 font-medium">{DAYS[hour.dayOfWeek]}</span>
                <label className="flex items-center gap-1.5 text-sm text-gray-600">
                  <input
                    type="checkbox"
                    checked={hour.isClosed}
                    onChange={(e) => updateHour(idx, 'isClosed', e.target.checked)}
                  />
                  Closed
                </label>
                {!hour.isClosed && (
                  <>
                    <input
                      type="time"
                      value={hour.openTime ?? ''}
                      onChange={(e) => updateHour(idx, 'openTime', e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-sm"
                    />
                    <span className="text-gray-400 text-sm">–</span>
                    <input
                      type="time"
                      value={hour.closeTime ?? ''}
                      onChange={(e) => updateHour(idx, 'closeTime', e.target.value)}
                      className="border border-gray-300 rounded px-2 py-1 text-sm"
                    />
                  </>
                )}
              </div>
            ))}
          </div>
        </section>

        {successMsg != null && (
          <p className="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-4 py-2">
            ✓ {successMsg}
          </p>
        )}

        <button
          type="submit"
          disabled={updateMutation.isPending}
          className="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
        >
          {updateMutation.isPending ? 'Saving…' : 'Save Configuration'}
        </button>
      </form>
    </div>
  )
}
