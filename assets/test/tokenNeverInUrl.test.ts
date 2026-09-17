import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchDetailRow } from '../src/functions/fetchDetailRow'
import { fetchEditForm } from '../src/functions/fetchEditForm'

describe('action token transport', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it.each([
    ['fetchDetailRow', fetchDetailRow, '/datatables/ajax/detail'],
    ['fetchEditForm', fetchEditForm, '/datatables/ajax/edit-form/view'],
  ])('%s posts the token in the body, never in the URL', async (_name, fetcher, url) => {
    const fetchMock = vi.fn().mockResolvedValue(new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await fetcher({ dataTable: 'signed-token', id: '42' })

    const [calledUrl, init] = fetchMock.mock.calls[0]

    expect(calledUrl).toBe(url)
    expect(calledUrl).not.toContain('signed-token')
    expect(init.method).toBe('POST')
    expect(JSON.parse(init.body)).toEqual({ dataTable: 'signed-token', id: '42' })
  })
})
