import { afterEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../src/functions/deleteEntity.js'
import { fetchDetailRow } from '../src/functions/fetchDetailRow.js'
import { fetchEditForm } from '../src/functions/fetchEditForm.js'
import { submitEditForm } from '../src/functions/submitEditForm.js'

describe('row action requests', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    function stubFetch(): ReturnType<typeof vi.fn> {
        const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }))
        vi.stubGlobal('fetch', fetchMock)

        return fetchMock
    }

    it('calls the default paths when no URL is given', async () => {
        const fetchMock = stubFetch()

        await deleteEntity({ dataTable: 't', id: '1' })
        await fetchDetailRow({ dataTable: 't', id: '1' })
        await fetchEditForm({ dataTable: 't', id: '1' })
        await submitEditForm({ dataTable: 't', id: '1', formData: {} })

        expect(fetchMock.mock.calls.map((call) => call[0])).toEqual([
            '/datatables/ajax/delete',
            '/datatables/ajax/detail',
            '/datatables/ajax/edit-form/view',
            '/datatables/ajax/edit-form',
        ])
    })

    it('calls the given URL and keeps it out of the body', async () => {
        const fetchMock = stubFetch()

        await deleteEntity({ dataTable: 't', id: '1', url: '/admin/delete' })
        await fetchDetailRow({ dataTable: 't', id: '1', url: '/admin/detail' })
        await fetchEditForm({ dataTable: 't', id: '1', url: '/admin/form' })
        await submitEditForm({ dataTable: 't', id: '1', formData: {}, url: '/admin/submit' })

        expect(fetchMock.mock.calls.map((call) => call[0])).toEqual([
            '/admin/delete',
            '/admin/detail',
            '/admin/form',
            '/admin/submit',
        ])
        for (const call of fetchMock.mock.calls) {
            expect(JSON.parse((call[1] as RequestInit).body as string)).not.toHaveProperty('url')
        }
    })
})
