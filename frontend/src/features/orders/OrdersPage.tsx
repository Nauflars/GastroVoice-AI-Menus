import { useState } from 'react'
import { useOrders, useUpdateOrderStatus } from './useOrders'
import type { Order, OrderStatus } from './types'

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: '⏳ Pending',
  confirmed: '✓ Confirmed',
  preparing: '🍳 Preparing',
  ready: '🔔 Ready',
  delivered: '✅ Delivered',
  cancelled: '✗ Cancelled',
}

const STATUS_COLORS: Record<OrderStatus, string> = {
  pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  confirmed: 'bg-blue-100 text-blue-800 border-blue-200',
  preparing: 'bg-orange-100 text-orange-800 border-orange-200',
  ready: 'bg-green-100 text-green-800 border-green-200',
  delivered: 'bg-gray-100 text-gray-600 border-gray-200',
  cancelled: 'bg-red-100 text-red-700 border-red-200',
}

const NEXT_STATUS: Partial<Record<OrderStatus, OrderStatus>> = {
  pending: 'confirmed',
  confirmed: 'preparing',
  preparing: 'ready',
  ready: 'delivered',
}

function OrderCard({ order }: { order: Order }) {
  const updateStatus = useUpdateOrderStatus()

  const nextStatus = NEXT_STATUS[order.status]

  return (
    <div className={`bg-white rounded-xl border p-5 space-y-3 ${
      order.status === 'cancelled' ? 'opacity-60' : ''
    }`}>
      {/* Header */}
      <div className="flex items-start justify-between gap-2">
        <div>
          <div className="flex items-center gap-2">
            <span className="font-mono text-xs text-gray-400">#{order.id.slice(0, 8)}</span>
            {order.source === 'phone' && (
              <span className="text-xs bg-purple-100 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-full font-medium">
                📞 Phone
              </span>
            )}
          </div>
          <div className="flex items-center gap-3 mt-1">
            {order.tableNumber != null && (
              <span className="text-sm text-gray-700">Table {order.tableNumber}</span>
            )}
            {order.customerPhone != null && (
              <span className="text-sm text-gray-700">📱 {order.customerPhone}</span>
            )}
          </div>
        </div>
        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full border ${STATUS_COLORS[order.status]}`}>
          {STATUS_LABELS[order.status]}
        </span>
      </div>

      {/* Items */}
      <div className="divide-y divide-gray-100">
        {order.lines.map((line) => (
          <div key={line.id} className="py-1.5 flex justify-between items-center">
            <span className="text-sm text-gray-800">
              <span className="font-medium">{line.quantity}×</span> {line.menuItemName}
            </span>
            <span className="text-sm text-gray-600">
              {(line.unitPrice * line.quantity).toFixed(2)} {line.currency}
            </span>
          </div>
        ))}
      </div>

      {/* Footer */}
      <div className="flex items-center justify-between pt-2 border-t border-gray-100">
        <div>
          <span className="font-bold text-gray-900">{order.total.toFixed(2)} {order.currency}</span>
          <span className="text-xs text-gray-400 ml-2">
            {new Date(order.createdAt).toLocaleString()}
          </span>
        </div>
        <div className="flex gap-2">
          {nextStatus != null && (
            <button
              onClick={() => updateStatus.mutate({ orderId: order.id, status: nextStatus })}
              disabled={updateStatus.isPending}
              className="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              → {STATUS_LABELS[nextStatus].split(' ')[1]}
            </button>
          )}
          {order.status !== 'cancelled' && order.status !== 'delivered' && (
            <button
              onClick={() => updateStatus.mutate({ orderId: order.id, status: 'cancelled' })}
              disabled={updateStatus.isPending}
              className="text-xs border border-red-200 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
            >
              Cancel
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

const FILTER_OPTIONS: Array<{ label: string; value: OrderStatus | 'all' }> = [
  { label: 'All', value: 'all' },
  { label: '⏳ Pending', value: 'pending' },
  { label: '🍳 Preparing', value: 'preparing' },
  { label: '🔔 Ready', value: 'ready' },
  { label: '✅ Delivered', value: 'delivered' },
  { label: '✗ Cancelled', value: 'cancelled' },
]

export default function OrdersPage() {
  const [filter, setFilter] = useState<OrderStatus | 'all'>('all')
  const { data: orders, isLoading, isError } = useOrders(
    filter !== 'all' ? filter : undefined,
  )

  const phoneOrders = orders?.filter((o) => o.source === 'phone') ?? []
  const phoneOrderCount = phoneOrders.length

  return (
    <div className="max-w-3xl">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Orders</h1>
          {phoneOrderCount > 0 && (
            <p className="text-sm text-purple-600 mt-0.5">
              📞 {phoneOrderCount} order{phoneOrderCount > 1 ? 's' : ''} from the phone system
            </p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs text-gray-400">Auto-refreshes every 30s</span>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg mb-6 flex-wrap">
        {FILTER_OPTIONS.map((opt) => (
          <button
            key={opt.value}
            onClick={() => setFilter(opt.value)}
            className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
              filter === opt.value
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            {opt.label}
          </button>
        ))}
      </div>

      {isLoading && <p className="text-gray-500">Loading orders…</p>}
      {isError && <p className="text-red-500">Failed to load orders.</p>}

      {orders != null && orders.length === 0 && (
        <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p className="text-gray-500">No orders found.</p>
          <p className="text-sm text-gray-400 mt-1">
            Orders placed via the phone system will appear here automatically.
          </p>
        </div>
      )}

      {orders != null && orders.length > 0 && (
        <div className="space-y-4">
          {orders.map((order) => (
            <OrderCard key={order.id} order={order} />
          ))}
        </div>
      )}
    </div>
  )
}
