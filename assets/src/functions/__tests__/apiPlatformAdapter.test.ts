import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiPlatformAdapter, type ColumnConfig } from '../apiPlatformAdapter.js'

const columns: ColumnConfig[] = [
    { data: 'avatar', field: 'avatar', name: 'avatar' },
    { data: 'email', field: 'email', name: 'email' },
]

describe('ApiPlatformAdapter', () => {
    it('maps DataTables global search to the default API Platform query parameter', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                search: { value: 'john' },
            })
        ).toMatchObject({
            page: '1',
            itemsPerPage: '25',
            q: 'john',
        })
    })

    it('does not send an empty global search parameter', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                search: { value: '   ' },
            })
        ).toEqual({
            page: '1',
            itemsPerPage: '25',
        })
    })

    it('keeps column-specific search mapping', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                columns: [{ search: { value: '' } }, { search: { value: 'admin@example.com' } }],
            })
        ).toMatchObject({
            email: 'admin@example.com',
        })
    })
})

describe('filter bar values', () => {
    it('flattens filter values into API Platform query parameters', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])

        const params = adapter.buildRequestParams({
            start: 0,
            length: 25,
            filters: {
                status: 'active',
                roles: ['ROLE_ADMIN', '', 'ROLE_USER'],
                createdAt: { from: '2026-01-01', to: '2026-02-01' },
                empty: '   ',
            },
        })

        expect(params).toEqual({
            page: '1',
            itemsPerPage: '25',
            status: 'active',
            'roles[0]': 'ROLE_ADMIN',
            'roles[1]': 'ROLE_USER',
            'createdAt[after]': '2026-01-01',
            'createdAt[before]': '2026-02-01',
        })
    })

    it('sends filters added by the filter bar through the configured data callback', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])
        const payload: Record<string, unknown> = {
            ajax: { url: '/api/users' },
            columns: [{ name: 'email' }],
        }

        adapter.configure(payload)

        const ajaxConfig = payload.ajax as {
            data: ((params: unknown) => Record<string, string>) & { consumesFilters?: boolean }
        }

        expect(ajaxConfig.data.consumesFilters).toBe(true)
        expect(ajaxConfig.data({ draw: 1, start: 0, length: 10, filters: { status: 'active' } })).toEqual(
            {
                page: '1',
                itemsPerPage: '10',
                status: 'active',
            }
        )
    })
})

describe('filter parameter collisions', () => {
    it('keeps protocol parameters when a filter shares their name', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])

        const params = adapter.buildRequestParams({
            start: 50,
            length: 25,
            filters: { page: '2', itemsPerPage: '100', status: 'active' },
        })

        expect(params).toEqual({
            page: '3',
            itemsPerPage: '25',
            status: 'active',
        })
    })

    it('keeps filters when a user ajax.data callback returns a replacement object', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])
        const payload: Record<string, unknown> = {
            ajax: {
                url: '/api/users',
                data: (params: Record<string, unknown>) => ({
                    draw: params.draw,
                    start: params.start,
                    length: params.length,
                }),
            },
            columns: [{ name: 'email' }],
        }

        adapter.configure(payload)

        const ajaxConfig = payload.ajax as { data: (params: unknown) => Record<string, string> }

        expect(
            ajaxConfig.data({ draw: 1, start: 0, length: 10, filters: { status: 'active' } })
        ).toEqual({
            page: '1',
            itemsPerPage: '10',
            status: 'active',
        })
    })
})

describe('column resolution by name', () => {
    it('resolves order columns by name when the client prepends a select column', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                columns: [
                    { data: null },
                    { data: 'avatar', name: 'avatar' },
                    { data: 'email', name: 'email' },
                ],
                order: [{ column: 1, dir: 'asc' }],
            })
        ).toMatchObject({
            'order[avatar]': 'asc',
        })
    })

    it('skips an order entry whose column cannot be resolved', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                order: [{ column: 7, dir: 'asc' }],
            })
        ).toEqual({
            page: '1',
            itemsPerPage: '25',
        })
    })

    it('maps column searches by name when indexes are shifted', () => {
        const adapter = new ApiPlatformAdapter(columns)

        expect(
            adapter.buildRequestParams({
                start: 0,
                length: 25,
                columns: [
                    { data: null },
                    { data: 'avatar', name: 'avatar' },
                    { data: 'email', name: 'email', search: { value: 'admin@example.com' } },
                ],
            })
        ).toEqual({
            page: '1',
            itemsPerPage: '25',
            email: 'admin@example.com',
        })
    })
})

describe('template rendering failures', () => {
    const templateRendering = { table: 'users', url: '/datatables/ajax/templates' }
    const renderedResponse = {
        draw: 1,
        recordsTotal: 1,
        recordsFiltered: 1,
        data: [{ id: 1, email: 'admin@example.com' }],
    }

    afterEach(() => {
        vi.unstubAllGlobals()
        vi.restoreAllMocks()
    })

    it('returns the original rows when template rendering responds with an error status', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                status: 403,
                json: () => Promise.reject(new Error('Body must not be read.')),
            })
        )

        const adapter = new ApiPlatformAdapter(columns)

        await expect(
            adapter.renderTemplateRows(renderedResponse, templateRendering)
        ).resolves.toEqual(renderedResponse)
        expect(warn).toHaveBeenCalledOnce()
    })

    it('returns the original rows when the template rendering request rejects', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

        const adapter = new ApiPlatformAdapter(columns)

        await expect(
            adapter.renderTemplateRows(renderedResponse, templateRendering)
        ).resolves.toEqual(renderedResponse)
        expect(warn).toHaveBeenCalledOnce()
    })

    it('returns the original rows when the template rendering body is not JSON', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                status: 200,
                json: () => Promise.reject(new SyntaxError('Unexpected token < in JSON.')),
            })
        )

        const adapter = new ApiPlatformAdapter(columns)

        await expect(
            adapter.renderTemplateRows(renderedResponse, templateRendering)
        ).resolves.toEqual(renderedResponse)
    })
})
