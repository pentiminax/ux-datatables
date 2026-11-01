import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../deleteEntity.js'
import { fetchDetailRow } from '../fetchDetailRow.js'
import { fetchEditForm } from '../fetchEditForm.js'
import { forwardedQuery } from '../forwardedQuery.js'
import { submitEditForm } from '../submitEditForm.js'
import { toggleBooleanValue } from '../toggleBooleanValue.js'

function sentBody(): Record<string, unknown> {
    const init = (fetch as any).mock.calls[0][1] as RequestInit

    return JSON.parse(init.body as string)
}

describe('forwarded query on single-row calls', () => {
    const payload = {
        forwardedQuery: { project: '7' },
        ajax: { data: { project: '7', search: 'x' } },
    }

    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: true }) })
        )
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('reads only the forwarded parameters from the payload', () => {
        expect(forwardedQuery(payload)).toEqual({ project: '7' })
        expect(forwardedQuery({})).toEqual({})
    })

    it.each([
        [
            'deleteEntity',
            () => deleteEntity({ dataTable: 't', id: '1', query: forwardedQuery(payload) }),
        ],
        [
            'toggleBooleanValue',
            () =>
                toggleBooleanValue({
                    url: '/u',
                    id: '1',
                    field: 'active',
                    newValue: true,
                    dataTable: 't',
                    query: forwardedQuery(payload),
                }),
        ],
        [
            'fetchDetailRow',
            () => fetchDetailRow({ dataTable: 't', id: '1', query: forwardedQuery(payload) }),
        ],
        [
            'fetchEditForm',
            () => fetchEditForm({ dataTable: 't', id: '1', query: forwardedQuery(payload) }),
        ],
        [
            'submitEditForm',
            () =>
                submitEditForm({
                    dataTable: 't',
                    id: '1',
                    formData: {},
                    query: forwardedQuery(payload),
                }),
        ],
    ])('%s sends the query field', async (_name, call) => {
        await call()

        expect(sentBody().query).toEqual({ project: '7' })
    })
})
