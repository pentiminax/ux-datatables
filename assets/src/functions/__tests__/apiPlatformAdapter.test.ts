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
                columns: [
                    { search: { value: '' } },
                    { search: { value: 'admin@example.com' } },
                ],
            })
        ).toMatchObject({
            email: 'admin@example.com',
        })
    })
})
