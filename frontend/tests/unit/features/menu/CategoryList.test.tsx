import { render, screen, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, it, expect, vi } from 'vitest'
import CategoryList from '../../src/features/menu/components/CategoryList'
import type { MenuCategoryWithItems } from '../../src/features/menu/types'

vi.mock('../../src/features/menu/useMenuMutations', () => ({
  useDeactivateCategory: () => ({ mutate: vi.fn() }),
  useToggleMenuItemAvailability: () => ({ mutate: vi.fn() }),
}))

const wrapper = ({ children }: { children: React.ReactNode }) => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>
}

const mockCategories: MenuCategoryWithItems[] = [
  {
    id: '1',
    name: 'Starters',
    description: null,
    sortOrder: 0,
    items: [
      {
        id: 'i1',
        categoryId: '1',
        name: 'Soup',
        description: 'Hot soup',
        price: 8.5,
        currency: 'EUR',
        isAvailable: true,
        allergens: [],
        imageUrl: null,
      },
      {
        id: 'i2',
        categoryId: '1',
        name: 'Salad',
        description: null,
        price: 6.0,
        currency: 'EUR',
        isAvailable: false,
        allergens: [],
        imageUrl: null,
      },
    ],
  },
]

describe('CategoryList', () => {
  it('renders category names', () => {
    render(
      <CategoryList categories={mockCategories} onEditCategory={vi.fn()} onAddItem={vi.fn()} />,
      { wrapper },
    )
    expect(screen.getByText('Starters')).toBeTruthy()
  })

  it('shows item count', () => {
    render(
      <CategoryList categories={mockCategories} onEditCategory={vi.fn()} onAddItem={vi.fn()} />,
      { wrapper },
    )
    expect(screen.getByText('(2 items)')).toBeTruthy()
  })

  it('expands category on click to show items', () => {
    render(
      <CategoryList categories={mockCategories} onEditCategory={vi.fn()} onAddItem={vi.fn()} />,
      { wrapper },
    )
    fireEvent.click(screen.getByText('Starters'))
    expect(screen.getByText('Soup')).toBeTruthy()
    expect(screen.getByText('Salad')).toBeTruthy()
  })

  it('shows empty message when no categories', () => {
    render(
      <CategoryList categories={[]} onEditCategory={vi.fn()} onAddItem={vi.fn()} />,
      { wrapper },
    )
    expect(screen.getByText('No categories yet.')).toBeTruthy()
  })
})
