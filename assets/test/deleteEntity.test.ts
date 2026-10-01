import { afterEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../src/functions/deleteEntity'

describe('deleteEntity', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('keeps numeric-looking ids as strings', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        await deleteEntity({
            dataTable: 'signed-token',
            id: '42',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(
            '/datatables/ajax/delete',
            expect.objectContaining({
                body: JSON.stringify({
                    dataTable: 'signed-token',
                    id: '42',
                }),
            })
        )
    })

    it('does not round identifiers above Number.MAX_SAFE_INTEGER', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        await deleteEntity({
            dataTable: 'signed-token',
            id: '9007199254740993',
        })

        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string).id).toBe('9007199254740993')
    })

    it('preserves zero-padded ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        await deleteEntity({
            dataTable: 'signed-token',
            id: '00123',
        })

        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string).id).toBe('00123')
    })

    it('preserves non-numeric ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        await deleteEntity({
            dataTable: 'signed-token',
            id: 'user-uuid-42',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(
            '/datatables/ajax/delete',
            expect.objectContaining({
                body: JSON.stringify({
                    dataTable: 'signed-token',
                    id: 'user-uuid-42',
                }),
            })
        )
    })
})
