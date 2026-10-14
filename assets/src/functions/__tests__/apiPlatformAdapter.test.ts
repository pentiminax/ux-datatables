import { describe, expect, it } from 'vitest'
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
                createdAt: { from: '2026-01-01', to: '2026-01-31' },
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
            'createdAt[strictly_before]': '2026-02-01',
        })
    })

    it('keeps the end day for date-only upper bounds before year 100', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])

        const params = adapter.buildRequestParams({
            start: 0,
            length: 25,
            filters: { createdAt: { to: '0050-12-31' } },
        })

        expect(params['createdAt[strictly_before]']).toBe('0051-01-01')
    })

    it('keeps a five-digit year for the upper bound past 9999-12-31', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])

        const params = adapter.buildRequestParams({
            start: 0,
            length: 25,
            filters: { createdAt: { to: '9999-12-31' } },
        })

        expect(params['createdAt[strictly_before]']).toBe('10000-01-01')
    })

    it('keeps a time-bearing range upper bound as inclusive before', () => {
        const adapter = new ApiPlatformAdapter([{ name: 'email' }])

        const params = adapter.buildRequestParams({
            start: 0,
            length: 25,
            filters: {
                createdAt: { from: '2026-01-01T00:00:00', to: '2026-01-31T14:30:00' },
            },
        })

        expect(params).toEqual({
            page: '1',
            itemsPerPage: '25',
            'createdAt[after]': '2026-01-01T00:00:00',
            'createdAt[before]': '2026-01-31T14:30:00',
        })
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

    it('keeps Next available when Hydra omits totalItems but advertises hydra:next', () => {
        const adapter = new ApiPlatformAdapter(columns)
        adapter.toRequestParams({ start: 0, length: 10, draw: 1 })

        expect(
            adapter.buildResponse(
                {
                    'hydra:member': Array.from({ length: 10 }, (_, index) => ({ id: index })),
                    'hydra:view': { 'hydra:next': '/api/books?page=2' },
                },
                1
            )
        ).toMatchObject({
            recordsTotal: 11,
            recordsFiltered: 11,
        })
    })

    it('keeps Next available when Hydra omits totalItems and has no view', () => {
        const adapter = new ApiPlatformAdapter(columns)
        adapter.toRequestParams({ start: 0, length: 10, draw: 1 })

        expect(
            adapter.buildResponse(
                {
                    'hydra:member': Array.from({ length: 10 }, (_, index) => ({ id: index })),
                },
                1
            )
        ).toMatchObject({
            recordsTotal: 11,
            recordsFiltered: 11,
        })
    })

    it('ends paging when Hydra omits totalItems and has no next link', () => {
        const adapter = new ApiPlatformAdapter(columns)
        adapter.toRequestParams({ start: 20, length: 10, draw: 3 })

        expect(
            adapter.buildResponse(
                {
                    'hydra:member': [{ id: 1 }, { id: 2 }, { id: 3 }],
                    'hydra:view': { 'hydra:first': '/api/books?page=1' },
                },
                3
            )
        ).toMatchObject({
            recordsTotal: 23,
            recordsFiltered: 23,
        })
    })

    it('still honors an explicit hydra:totalItems of zero', () => {
        const adapter = new ApiPlatformAdapter(columns)
        adapter.toRequestParams({ start: 0, length: 10, draw: 1 })

        expect(
            adapter.buildResponse(
                {
                    'hydra:member': [],
                    'hydra:totalItems': 0,
                },
                1
            )
        ).toMatchObject({
            recordsTotal: 0,
            recordsFiltered: 0,
        })
    })
})
