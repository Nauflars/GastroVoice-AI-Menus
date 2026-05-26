import { useState } from 'react'
import type { MenuCategoryWithItems } from '../types'
import { useDeactivateCategory, useToggleMenuItemAvailability } from '../useMenuMutations'

interface Props {
  categories: MenuCategoryWithItems[]
  onEditCategory: (id: string, name: string) => void
  onAddItem: (categoryId: string) => void
}

export default function CategoryList({ categories, onEditCategory, onAddItem }: Props) {
  const [expanded, setExpanded] = useState<Set<string>>(new Set())
  const deactivate = useDeactivateCategory()
  const toggleAvailability = useToggleMenuItemAvailability()

  const toggle = (id: string) => {
    setExpanded((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  if (categories.length === 0) {
    return <p className="text-gray-400 text-sm">No categories yet.</p>
  }

  return (
    <div className="space-y-3">
      {categories.map((cat) => (
        <div key={cat.id} className="bg-white border border-gray-200 rounded-xl overflow-hidden">
          {/* Category header */}
          <div
            className="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50"
            onClick={() => toggle(cat.id)}
          >
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-400">{expanded.has(cat.id) ? '▼' : '▶'}</span>
              <h3 className="font-semibold text-gray-900">{cat.name}</h3>
              <span className="text-xs text-gray-400">({cat.items.length} items)</span>
            </div>
            <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
              <button
                className="text-xs text-blue-600 hover:text-blue-800 px-2 py-1"
                onClick={() => onEditCategory(cat.id, cat.name)}
              >
                Edit
              </button>
              <button
                className="text-xs text-red-500 hover:text-red-700 px-2 py-1"
                onClick={() => deactivate.mutate(cat.id)}
              >
                Delete
              </button>
              <button
                className="text-xs text-green-600 hover:text-green-800 px-2 py-1"
                onClick={() => onAddItem(cat.id)}
              >
                + Item
              </button>
            </div>
          </div>

          {/* Items */}
          {expanded.has(cat.id) && (
            <div className="border-t border-gray-100">
              {cat.items.length === 0 ? (
                <p className="px-4 py-3 text-sm text-gray-400">No items in this category.</p>
              ) : (
                <div className="divide-y divide-gray-50">
                  {cat.items.map((item) => (
                    <div
                      key={item.id}
                      className={`px-4 py-3 flex justify-between items-center ${
                        !item.isAvailable ? 'opacity-50' : ''
                      }`}
                    >
                      <div>
                        <p className="text-sm font-medium text-gray-800">{item.name}</p>
                        {item.description && (
                          <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
                        )}
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold text-gray-700">
                          {item.price.toFixed(2)} {item.currency}
                        </span>
                        <button
                          className={`text-xs px-2 py-1 rounded ${
                            item.isAvailable
                              ? 'bg-green-50 text-green-700 hover:bg-green-100'
                              : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                          }`}
                          onClick={() => toggleAvailability.mutate(item.id)}
                        >
                          {item.isAvailable ? 'Available' : 'Unavailable'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}
