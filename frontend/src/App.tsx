import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import LoginPage from '@/pages/LoginPage'
import DashboardLayout from '@/layouts/DashboardLayout'
import RestaurantConfigPage from '@/features/restaurant/RestaurantConfigPage'
import MenuPage from '@/features/menu/MenuPage'
import MenuImportPage from '@/features/menu/MenuImportPage'
import OrdersPage from '@/features/orders/OrdersPage'
import VoiceSimulatorPage from '@/pages/VoiceSimulatorPage'
import ReservationsPage from '@/features/reservations/ReservationsPage'

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const token = useAuthStore((s) => s.token)
  return token != null ? <>{children}</> : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route
          path="/"
          element={
            <ProtectedRoute>
              <DashboardLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="/restaurant" replace />} />
          <Route path="restaurant" element={<RestaurantConfigPage />} />
          <Route path="menu" element={<MenuPage />} />
          <Route path="menu/import" element={<MenuImportPage />} />
          <Route path="reservations" element={<ReservationsPage />} />
          <Route path="orders" element={<OrdersPage />} />
          <Route path="voice" element={<VoiceSimulatorPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
