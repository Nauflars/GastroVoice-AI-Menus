import { BrowserRouter } from 'react-router-dom'
import ErrorBoundary from '@/shared/components/ErrorBoundary'
import Router from '@/app/Router'

export default function App() {
  return (
    <ErrorBoundary>
      <BrowserRouter>
        <Router />
      </BrowserRouter>
    </ErrorBoundary>
  )
}
