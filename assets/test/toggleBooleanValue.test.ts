import { afterEach, describe, expect, it, vi } from 'vitest'
import { toggleBooleanValue } from '../src/functions/toggleBooleanValue'

describe('toggleBooleanValue', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('keeps numeric-looking ids as strings', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: '42',
            field: 'enabled',
            newValue: true,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(
            '/datatables/ajax/edit',
            expect.objectContaining({
                body: JSON.stringify({
                    id: '42',
                    field: 'enabled',
                    newValue: true,
                    dataTable: 'signed-token',
                }),
            })
        )
    })

    it('does not round identifiers above Number.MAX_SAFE_INTEGER', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: '9007199254740993',
            field: 'enabled',
            newValue: true,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string).id).toBe('9007199254740993')
    })

    it('preserves zero-padded ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: '00123',
            field: 'enabled',
            newValue: true,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(JSON.parse(fetchMock.mock.calls[0][1].body as string).id).toBe('00123')
    })

    it('preserves non-numeric ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: 'user-uuid-42',
            field: 'enabled',
            newValue: false,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(
            '/datatables/ajax/edit',
            expect.objectContaining({
                body: JSON.stringify({
                    id: 'user-uuid-42',
                    field: 'enabled',
                    newValue: false,
                    dataTable: 'signed-token',
                }),
            })
        )
    })

    it('includes dataTable token in the body', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: '42',
            field: 'enabled',
            newValue: true,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(
            '/datatables/ajax/edit',
            expect.objectContaining({
                body: JSON.stringify({
                    id: '42',
                    field: 'enabled',
                    newValue: true,
                    dataTable: 'signed-token',
                }),
            })
        )
    })

    it('does not send entity or dataTableClass in the body', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
        vi.stubGlobal('fetch', fetchMock)

        await toggleBooleanValue({
            id: '42',
            field: 'enabled',
            newValue: true,
            url: '/datatables/ajax/edit',
            dataTable: 'signed-token',
        })

        expect(fetchMock).toHaveBeenCalledOnce()
        const body = JSON.parse(fetchMock.mock.calls[0][1].body as string)
        expect(body).toEqual({
            id: '42',
            field: 'enabled',
            newValue: true,
            dataTable: 'signed-token',
        })
        expect(body).not.toHaveProperty('entity')
        expect(body).not.toHaveProperty('dataTableClass')
    })
})
