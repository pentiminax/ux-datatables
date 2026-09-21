import { afterEach, describe, expect, it, vi } from 'vitest'
import { runBulkAction } from '../src/functions/runBulkAction'

describe('runBulkAction', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  const request = {
    url: '/datatables/ajax/bulk',
    dataTable: 'signed-token',
    action: 'approve',
    ids: ['1', '2', 'sku-9'],
    csrfToken: 'csrf-value',
  }

  it('posts the selection with the csrf header and the token in the body', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(new Response(JSON.stringify({ success: true, processed: 3, skipped: 1 })))
    vi.stubGlobal('fetch', fetchMock)

    const result = await runBulkAction(request)

    const [url, init] = fetchMock.mock.calls[0]

    expect(url).toBe('/datatables/ajax/bulk')
    expect(url).not.toContain('signed-token')
    expect(init.method).toBe('POST')
    expect(init.headers['X-CSRF-Token']).toBe('csrf-value')
    expect(JSON.parse(init.body)).toEqual({
      dataTable: 'signed-token',
      action: 'approve',
      ids: [1, 2, 'sku-9'],
      allMatching: false,
      deselectedIds: [],
      query: {},
    })
    expect(result).toEqual({ success: true, processed: 3, skipped: 1, message: undefined })
  })

  it('forwards the displayed request when every matching row is selected', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: true })))
    vi.stubGlobal('fetch', fetchMock)

    await runBulkAction({
      ...request,
      ids: [],
      allMatching: true,
      deselectedIds: ['4'],
      query: { search: { value: 'Beta' } },
    })

    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toMatchObject({
      allMatching: true,
      deselectedIds: [4],
      query: { search: { value: 'Beta' } },
    })
  })

  it('reports a failure without reading the body', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('nope', { status: 403 })))

    expect(await runBulkAction(request)).toEqual({ success: false, processed: 0, skipped: 0 })
  })
})
