import { useMenu } from './useMenu'

export default function MenuPage() {
  const { data: categories, isLoading, isError } = useMenu()

  if (isLoading) return <p className="text-gray-500">Loading menu…</p>
  if (isError) return <p className="text-red-500">Failed to load menu.</p>

  if (categories == null || categories.length === 0) {
    return (
      <div className="max-w-2xl">
        <h1 className="text-2xl font-bold text-gray-900 mb-4">Menu</h1>
        <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p className="text-gray-500 text-lg">No menu items yet.</p>
          <p className="text-gray-400 mt-2 text-sm">
            Use <strong>AI Menu Import</strong> to analyze a menu image and create all dishes automatically.
          </p>
          <a
            href="/menu/import"
            className="inline-block mt-4 bg-blue-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors"
          >
            🤖 Import Menu with AI
          </a>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-3xl">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Menu</h1>
        <a
          href="/menu/import"
          className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
        >
          🤖 AI Import
        </a>
      </div>

      <div className="space-y-8">
        {categories.map((category) => (
          <section key={category.id}>
            <h2 className="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">
              {category.name}
            </h2>
            {category.items.length === 0 ? (
              <p className="text-sm text-gray-400">No items in this category.</p>
            ) : (
              <div className="grid gap-3">
                {category.items.map((item) => (
                  <div
                    key={item.id}
                    className={`bg-white rounded-xl border p-4 flex justify-between items-start gap-4 ${
                      item.isAvailable ? 'border-gray-200' : 'border-gray-100 opacity-50'
                    }`}
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <p className="font-medium text-gray-900">{item.name}</p>
                        {!item.isAvailable && (
                          <span className="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded">
                            Unavailable
                          </span>
                        )}
                      </div>
                      {item.description != null && (
                        <p className="text-sm text-gray-500 mt-1">{item.description}</p>
                      )}
                      {(item.allergens ?? []).length > 0 && (
                        <p className="text-xs text-amber-600 mt-1">
                          ⚠ {(item.allergens ?? []).join(', ')}
                        </p>
                      )}
                    </div>
                    <p className="font-semibold text-gray-900 whitespace-nowrap">
                      {item.price.toFixed(2)} {item.currency}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </section>
        ))}
      </div>
    </div>
  )
}
