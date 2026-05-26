import { render, screen, fireEvent } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import MenuItemForm from '../../src/features/menu/components/MenuItemForm'

describe('MenuItemForm', () => {
  it('does not render when closed', () => {
    const { container } = render(
      <MenuItemForm
        isOpen={false}
        categoryId="cat-1"
        onClose={vi.fn()}
        onSubmit={vi.fn()}
      />,
    )
    expect(container.innerHTML).toBe('')
  })

  it('renders form when open', () => {
    render(
      <MenuItemForm
        isOpen={true}
        categoryId="cat-1"
        onClose={vi.fn()}
        onSubmit={vi.fn()}
      />,
    )
    expect(screen.getByText('New Item')).toBeTruthy()
    expect(screen.getByPlaceholderText('e.g. Margherita Pizza')).toBeTruthy()
  })

  it('shows Edit title when initial data provided', () => {
    render(
      <MenuItemForm
        isOpen={true}
        categoryId="cat-1"
        initial={{ name: 'Soup', description: 'Hot', price: 8.5, currency: 'EUR' }}
        onClose={vi.fn()}
        onSubmit={vi.fn()}
      />,
    )
    expect(screen.getByText('Edit Item')).toBeTruthy()
    expect(screen.getByDisplayValue('Soup')).toBeTruthy()
  })

  it('calls onSubmit with correct data', () => {
    const onSubmit = vi.fn()
    render(
      <MenuItemForm
        isOpen={true}
        categoryId="cat-1"
        onClose={vi.fn()}
        onSubmit={onSubmit}
      />,
    )

    fireEvent.change(screen.getByPlaceholderText('e.g. Margherita Pizza'), {
      target: { value: 'Tiramisu' },
    })
    fireEvent.change(screen.getByPlaceholderText('0.00'), {
      target: { value: '7.50' },
    })
    fireEvent.click(screen.getByText('Add Item'))

    expect(onSubmit).toHaveBeenCalledWith({
      categoryId: 'cat-1',
      name: 'Tiramisu',
      description: null,
      price: 7.5,
      currency: 'EUR',
    })
  })

  it('calls onClose when cancel clicked', () => {
    const onClose = vi.fn()
    render(
      <MenuItemForm
        isOpen={true}
        categoryId="cat-1"
        onClose={onClose}
        onSubmit={vi.fn()}
      />,
    )
    fireEvent.click(screen.getByText('Cancel'))
    expect(onClose).toHaveBeenCalled()
  })
})
