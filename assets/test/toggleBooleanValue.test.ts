import { afterEach, describe, expect, it, vi } from 'vitest'
import { toggleBooleanValue } from '../src/functions/toggleBooleanValue'

describe('toggleBooleanValue', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends numeric ids as numbers', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await toggleBooleanValue({
      id: '42',
      entity: 'App\\Entity\\User',
      field: 'enabled',
      newValue: true,
      url: '/datatables/ajax/edit',
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/edit',
      expect.objectContaining({
        body: JSON.stringify({
          id: 42,
          entity: 'App\\Entity\\User',
          field: 'enabled',
          newValue: true,
        }),
      })
    )
  })

  it('preserves non-numeric ids', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await toggleBooleanValue({
      id: 'user-uuid-42',
      entity: 'App\\Entity\\User',
      field: 'enabled',
      newValue: false,
      url: '/datatables/ajax/edit',
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/edit',
      expect.objectContaining({
        body: JSON.stringify({
          id: 'user-uuid-42',
          entity: 'App\\Entity\\User',
          field: 'enabled',
          newValue: false,
        }),
      })
    )
  })
})
