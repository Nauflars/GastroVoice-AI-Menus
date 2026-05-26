import { useState } from 'react'
import { useReservations, useCancelReservation } from './useReservations'
import type { Reservation } from './types'

function getWeekDays(startDate: Date): Date[] {
  const days: Date[] = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(startDate)
    d.setDate(startDate.getDate() + i)
    days.push(d)
  }
  return days
}

function toYMD(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function getMondayOfWeek(date: Date): Date {
  const d = new Date(date)
  const day = d.getDay()
  const diff = day === 0 ? -6 : 1 - day
  d.setDate(d.getDate() + diff)
  d.setHours(0, 0, 0, 0)
  return d
}

const STATUS_COLORS: Record<string, string> = {
  confirmed: 'bg-green-50 border-green-200 text-green-800',
  pending:   'bg-yellow-50 border-yellow-200 text-yellow-800',
  cancelled: 'bg-gray-50 border-gray-200 text-gray-400 line-through',
}

const STATUS_BADGE: Record<string, string> = {
  confirmed: 'bg-green-100 text-green-700',
  pending:   'bg-yellow-100 text-yellow-700',
  cancelled: 'bg-gray-100 text-gray-500',
}

const DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

export default function ReservationsPage() {
  const [weekStart, setWeekStart] = useState(() => getMondayOfWeek(new Date()))
  const { data: reservations = [], isLoading, isError } = useReservations()
  const cancel = useCancelReservation()

  const days = getWeekDays(weekStart)

  const byDay: Record<string, Reservation[]> = {}
  for (const r of reservations) {
    if (!byDay[r.date]) byDay[r.date] = []
    byDay[r.date].push(r)
  }
  for (const key of Object.keys(byDay)) {
    byDay[key].sort((a, b) => a.timeSlot.localeCompare(b.timeSlot))
  }

  const prevWeek = () => {
    const d = new Date(weekStart)
    d.setDate(d.getDate() - 7)
    setWeekStart(d)
  }
  const nextWeek = () => {
    const d = new Date(weekStart)
    d.setDate(d.getDate() + 7)
    setWeekStart(d)
  }
  const goToday = () => setWeekStart(getMondayOfWeek(new Date()))

  const weekEnd = days[6]
  const todayYMD = toYMD(new Date())

  const totalThisWeek = days.reduce((acc, d) => acc + (byDay[toYMD(d)]?.filter(r => r.status !== 'cancelled').length ?? 0), 0)

  return (
    <div className="max-w-6xl">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Reservas</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {weekStart.toLocaleDateString('es-ES', { day: 'numeric', month: 'long' })}
            {' — '}
            {weekEnd.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' })}
            <span className="ml-3 font-medium text-gray-700">{totalThisWeek} reservas activas</span>
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={goToday}
            className="text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
          >
            Hoy
          </button>
          <button
            onClick={prevWeek}
            className="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
            aria-label="Semana anterior"
          >
            ‹
          </button>
          <button
            onClick={nextWeek}
            className="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
            aria-label="Semana siguiente"
          >
            ›
          </button>
        </div>
      </div>

      {isLoading && (
        <p className="text-gray-500 text-center py-16">Cargando reservas…</p>
      )}
      {isError && (
        <p className="text-red-500 text-center py-16">Error al cargar las reservas.</p>
      )}

      {!isLoading && !isError && (
        <div className="grid grid-cols-7 gap-3">
          {days.map((day, idx) => {
            const ymd = toYMD(day)
            const dayReservations = byDay[ymd] ?? []
            const isToday = ymd === todayYMD
            const isPast = ymd < todayYMD

            return (
              <div key={ymd} className="min-h-[200px]">
                {/* Day header */}
                <div
                  className={`text-center py-2 rounded-t-lg mb-2 ${
                    isToday
                      ? 'bg-blue-600 text-white'
                      : isPast
                      ? 'bg-gray-100 text-gray-400'
                      : 'bg-gray-50 text-gray-700'
                  }`}
                >
                  <div className="text-xs font-medium uppercase tracking-wide">
                    {DAY_NAMES[idx]}
                  </div>
                  <div className={`text-lg font-bold ${isToday ? 'text-white' : ''}`}>
                    {day.getDate()}
                  </div>
                  {dayReservations.filter(r => r.status !== 'cancelled').length > 0 && (
                    <div className={`text-xs mt-0.5 ${isToday ? 'text-blue-200' : 'text-gray-500'}`}>
                      {dayReservations.filter(r => r.status !== 'cancelled').length} res.
                    </div>
                  )}
                </div>

                {/* Reservations */}
                <div className="space-y-1.5">
                  {dayReservations.length === 0 ? (
                    <div className="text-center text-xs text-gray-300 py-4">—</div>
                  ) : (
                    dayReservations.map((r) => (
                      <div
                        key={r.id}
                        className={`border rounded-lg p-2 text-xs ${STATUS_COLORS[r.status] ?? 'bg-white border-gray-200'}`}
                      >
                        <div className="flex items-center justify-between gap-1 mb-1">
                          <span className="font-semibold">{r.timeSlot}</span>
                          <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${STATUS_BADGE[r.status]}`}>
                            {r.numPeople}p
                          </span>
                        </div>
                        <div className="font-medium truncate" title={r.customerName}>
                          {r.customerName}
                        </div>
                        {r.customerPhone != null && (
                          <div className="text-gray-500 mt-0.5 flex items-center gap-1">
                            <span>📞</span>
                            <a
                              href={`tel:${r.customerPhone}`}
                              className="hover:underline truncate"
                              onClick={e => e.stopPropagation()}
                            >
                              {r.customerPhone}
                            </a>
                          </div>
                        )}
                        {r.notes != null && r.notes !== '' && (
                          <div className="text-gray-400 mt-0.5 truncate italic" title={r.notes}>
                            {r.notes}
                          </div>
                        )}
                        {r.status !== 'cancelled' && (
                          <button
                            onClick={() => {
                              if (confirm(`¿Cancelar la reserva de ${r.customerName}?`)) {
                                cancel.mutate(r.id)
                              }
                            }}
                            className="mt-1.5 text-red-400 hover:text-red-600 text-xs underline"
                          >
                            Cancelar
                          </button>
                        )}
                      </div>
                    ))
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
