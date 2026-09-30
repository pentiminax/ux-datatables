import { afterEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../src/functions/deleteEntity'

describe('deleteEntity', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('preserves numeric-looking ids as strings', async () => {
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

    it('preserves zero-padded and unsafe-integer ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        for (const id of ['00123', '9007199254740993', '1e3']) {
            fetchMock.mockClear()
            await deleteEntity({ dataTable: 'signed-token', id })

            expect(fetchMock).toHaveBeenCalledWith(
                '/datatables/ajax/delete',
                expect.objectContaining({
                    body: JSON.stringify({ dataTable: 'signed-token', id }),
                })
            )
        }
    })
})
