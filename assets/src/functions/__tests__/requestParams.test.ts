import { describe, expect, it, vi } from 'vitest'
import { installRequestParams } from '../requestParams.js'

const protocol = () => ({ draw: 2, start: 25, length: 25 })

describe('installRequestParams', () => {
    it('merges a static ajax.data object and the filter values into the request', () => {
        const payload: Record<string, any> = { ajax: { url: '/data', data: { extra: 'kept' } } }
        const handle = installRequestParams(payload, { filters: () => ({ name: 'john' }) })

        const sent = payload.ajax.data(protocol())

        expect(sent).toEqual({ ...protocol(), extra: 'kept', filters: { name: 'john' } })
        expect(handle.current()).toBe(sent)
    })

    it('lets the applied filter values win over static ajax.data filters', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/data', data: { filters: { name: 'static' } } },
        }
        const handle = installRequestParams(payload, { filters: () => ({ name: 'john' }) })

        expect(payload.ajax.data(protocol()).filters).toEqual({ name: 'john' })
        expect(handle.current().filters).toEqual({ name: 'john' })
    })

    it('reads the filter values at request time, not at install time', () => {
        let values: Record<string, unknown> = {}
        const payload: Record<string, any> = { ajax: { url: '/data' } }
        installRequestParams(payload, { filters: () => values })

        values = { status: 'done' }

        expect(payload.ajax.data(protocol()).filters).toEqual({ status: 'done' })
    })

    it('hands the filters and the DataTables settings to a user callback', () => {
        const userData = vi.fn((params: Record<string, any>) => {
            params.seen = params.filters
        })
        const payload: Record<string, any> = { ajax: { url: '/data', data: userData } }
        installRequestParams(payload, { filters: () => ({ name: 'john' }) })
        const settings = { nTable: null }

        const sent = payload.ajax.data(protocol(), settings)

        expect(userData).toHaveBeenCalledWith(expect.anything(), settings)
        expect(sent).toMatchObject({ seen: { name: 'john' }, filters: { name: 'john' } })
    })

    it('keeps the filters on a replacement object unless the callback set its own', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/data', data: (params: Record<string, any>) => ({ draw: params.draw }) },
        }
        installRequestParams(payload, { filters: () => ({ name: 'john' }) })

        expect(payload.ajax.data(protocol())).toEqual({ draw: 2, filters: { name: 'john' } })

        payload.ajax = {
            url: '/data',
            data: () => ({ draw: 1, filters: { name: 'own' } }),
        }
        installRequestParams(payload, { filters: () => ({ name: 'john' }) })

        expect(payload.ajax.data(protocol()).filters).toEqual({ name: 'own' })
    })

    it('sends a serialized callback result as is but remembers the params behind it', () => {
        const payload: Record<string, any> = {
            ajax: {
                url: '/data',
                data: (params: Record<string, any>) => {
                    params.scope = 'mine'

                    return JSON.stringify(params)
                },
            },
        }
        const handle = installRequestParams(payload, { filters: () => ({ status: 'draft' }) })

        const sent = payload.ajax.data(protocol())

        expect(sent).toBe(
            JSON.stringify({ ...protocol(), filters: { status: 'draft' }, scope: 'mine' })
        )
        // ajax.params() would hold the string here, so a select-all would replay no search and
        // no filters and act on the whole unfiltered table.
        expect(handle.current()).toEqual({
            ...protocol(),
            filters: { status: 'draft' },
            scope: 'mine',
        })
    })

    it('remembers what a JSON string sent, not the broader params behind it', () => {
        const payload: Record<string, any> = {
            ajax: {
                url: '/data',
                data: (params: Record<string, any>) =>
                    JSON.stringify({ ...params, search: { value: 'narrow' } }),
            },
        }
        const handle = installRequestParams(payload, { filters: () => ({ status: 'draft' }) })

        payload.ajax.data({ ...protocol(), search: { value: '' } })

        expect(handle.current()).toEqual({
            ...protocol(),
            search: { value: 'narrow' },
            filters: { status: 'draft' },
        })
    })

    it('falls back to the params behind a non-JSON string', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/data', data: (params: Record<string, any>) => `draw=${params.draw}` },
        }
        const handle = installRequestParams(payload)

        expect(payload.ajax.data(protocol())).toBe('draw=2')
        expect(handle.current()).toEqual(protocol())
    })

    it('sends the transport rewrite but remembers the DataTables-protocol params', () => {
        const transport = vi.fn((params: Record<string, any>) => ({
            page: String(params.start / params.length + 1),
            status: String(params.filters.status),
        }))
        const payload: Record<string, any> = {
            ajax: {
                url: '/api/books',
                data: (params: Record<string, any>) => ({ ...params, extra: 'kept' }),
            },
        }
        const handle = installRequestParams(payload, {
            filters: () => ({ status: 'active' }),
            transport,
        })

        expect(payload.ajax.data(protocol())).toEqual({ page: '2', status: 'active' })
        expect(handle.current()).toEqual({
            ...protocol(),
            extra: 'kept',
            filters: { status: 'active' },
        })
    })

    it('runs the transport on the params when the callback returns a string', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/api/books', data: (params: unknown) => JSON.stringify(params) },
        }
        installRequestParams(payload, {
            transport: (params) => ({ draw: String(params.draw) }),
        })

        expect(payload.ajax.data(protocol())).toEqual({ draw: '2' })
    })

    it('keeps the paging for the transport when a JSON string omits it', () => {
        const transport = vi.fn((params: Record<string, any>) => ({
            page: String(params.start / params.length + 1),
        }))
        const payload: Record<string, any> = {
            ajax: { url: '/api/books', data: () => JSON.stringify({ custom: 'body' }) },
        }
        const handle = installRequestParams(payload, { transport })

        expect(payload.ajax.data(protocol())).toEqual({ page: '2' })
        expect(handle.current()).toEqual({ ...protocol(), custom: 'body' })
    })

    it('hands the transport the search a JSON string narrowed', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/api/books', data: () => JSON.stringify({ search: { value: 'narrow' } }) },
        }
        const handle = installRequestParams(payload, {
            transport: (params) => ({
                page: String(params.start / params.length + 1),
                q: params.search.value,
            }),
        })

        expect(payload.ajax.data({ ...protocol(), search: { value: '' } })).toEqual({
            page: '2',
            q: 'narrow',
        })
        expect(handle.current().search).toEqual({ value: 'narrow' })
    })

    it('keeps the paging for the transport when a replacement object omits it', () => {
        const payload: Record<string, any> = {
            ajax: { url: '/api/books', data: () => ({ custom: 'body' }) },
        }
        installRequestParams(payload, {
            transport: (params) => ({ page: String(params.start / params.length + 1) }),
        })

        expect(payload.ajax.data(protocol())).toEqual({ page: '2' })
    })

    it('attaches the filters to a function ajax and remembers the request', () => {
        const ajax = vi.fn()
        const payload: Record<string, any> = { ajax }
        const handle = installRequestParams(payload, { filters: () => ({ name: 'john' }) })
        const callback = vi.fn()

        payload.ajax(protocol(), callback)

        expect(ajax).toHaveBeenCalledWith({ ...protocol(), filters: { name: 'john' } }, callback)
        expect(handle.current()).toEqual({ ...protocol(), filters: { name: 'john' } })
    })

    it('leaves a table without ajax untouched', () => {
        const payload: Record<string, any> = { data: [] }
        const handle = installRequestParams(payload, { filters: () => ({}) })

        expect(payload).toEqual({ data: [] })
        expect(handle.current()).toEqual({})
    })
})
