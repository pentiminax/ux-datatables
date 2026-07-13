import { afterEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../src/functions/deleteEntity'

describe('deleteEntity', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sends numeric ids as numbers', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await deleteEntity({
      entity: 'App\\Entity\\User',
      id: '42',
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/delete',
      expect.objectContaining({
        body: JSON.stringify({
          entity: 'App\\Entity\\User',
          id: 42,
        }),
      })
    )
  })

  it('preserves non-numeric ids', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await deleteEntity({
      entity: 'App\\Entity\\User',
      id: 'user-uuid-42',
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/delete',
      expect.objectContaining({
        body: JSON.stringify({
          entity: 'App\\Entity\\User',
          id: 'user-uuid-42',
        }),
      })
    )
  })

  it('includes dataTableClass in the body when provided', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await deleteEntity({
      entity: 'App\\Entity\\User',
      id: '42',
      dataTableClass: 'App\\DataTable\\UserDataTable',
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/delete',
      expect.objectContaining({
        body: JSON.stringify({
          entity: 'App\\Entity\\User',
          id: 42,
          dataTableClass: 'App\\DataTable\\UserDataTable',
        }),
      })
    )
  })

  it('omits dataTableClass from the body when null', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await deleteEntity({
      entity: 'App\\Entity\\User',
      id: '42',
      dataTableClass: null,
    })

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith(
      '/datatables/ajax/delete',
      expect.objectContaining({
        body: JSON.stringify({
          entity: 'App\\Entity\\User',
          id: 42,
        }),
      })
    )
  })
})
