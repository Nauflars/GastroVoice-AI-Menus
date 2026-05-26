import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

const navItems = [
  { to: '/restaurant', label: '⚙️ Restaurant' },
  { to: '/menu', label: '🍽 Menu' },
  { to: '/menu/import', label: '🤖 AI Menu Import' },
  { to: '/reservations', label: '📅 Reservas' },
  { to: '/orders', label: '📋 Orders' },
  { to: '/voice', label: '📞 Voice Simulator' },
]

export default function DashboardLayout() {
  const logout = useAuthStore((s) => s.logout)
  const email = useAuthStore((s) => s.email)
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <aside className="w-64 bg-white border-r border-gray-200 flex flex-col">
        <div className="p-6 border-b border-gray-200">
          <h1 className="text-xl font-bold text-blue-600">🍽 GastroVoice AI</h1>
          <p className="text-xs text-gray-500 mt-1 truncate">{email}</p>
        </div>
        <nav className="flex-1 p-4 space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `block px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-gray-700 hover:bg-gray-100'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="p-4 border-t border-gray-200">
          <button
            onClick={handleLogout}
            className="w-full text-sm text-gray-600 hover:text-red-600 py-2 text-left px-4 rounded-lg hover:bg-red-50 transition-colors"
          >
            🚪 Log out
          </button>
        </div>
      </aside>

      {/* Main content */}
      <main className="flex-1 overflow-y-auto p-8">
        <Outlet />
      </main>
    </div>
  )
}
