import { describe, expect, it } from 'vitest'
import { restrictSearchBuilder } from '../restrictSearchBuilder.js'

const columns = [{ data: 'id' }, { data: 'name', searchable: false }, { data: 'city' }]

describe('restrictSearchBuilder', () => {
    it('hides the without condition and the non-searchable columns', () => {
        const payload: Record<string, any> = { columns, serverSide: true, searchBuilder: {} }

        restrictSearchBuilder(payload)

        expect(payload.searchBuilder.conditions).toEqual({ array: { without: null } })
        expect(payload.searchBuilder.columns).toEqual([0, 2])
    })

    it('keeps user conditions and narrows user columns', () => {
        const payload: Record<string, any> = {
            columns,
            serverSide: true,
            searchBuilder: {
                columns: [1, 2],
                conditions: { num: { '!=': null }, array: { '!without': null } },
            },
        }

        restrictSearchBuilder(payload)

        expect(payload.searchBuilder.conditions).toEqual({
            num: { '!=': null },
            array: { '!without': null, without: null },
        })
        expect(payload.searchBuilder.columns).toEqual([2])
    })

    it('leaves a payload without SearchBuilder untouched', () => {
        const payload: Record<string, any> = { columns, serverSide: true }

        restrictSearchBuilder(payload)

        expect(payload).toEqual({ columns, serverSide: true })
    })

    it('leaves a client-side payload untouched', () => {
        const payload: Record<string, any> = { columns, searchBuilder: {} }

        restrictSearchBuilder(payload)

        expect(payload.searchBuilder).toEqual({})
    })

    it('excludes the select checkbox column unshifted at index 0', () => {
        const payload: Record<string, any> = {
            columns: [{ data: null, searchable: false }, ...columns],
            serverSide: true,
            searchBuilder: { columns: '*' },
        }

        restrictSearchBuilder(payload)

        expect(payload.searchBuilder.columns).toEqual([1, 3])
    })

    it('leaves any other columns selector untouched', () => {
        const payload: Record<string, any> = {
            columns,
            serverSide: true,
            searchBuilder: { columns: '.searchable' },
        }

        restrictSearchBuilder(payload)

        expect(payload.searchBuilder.columns).toBe('.searchable')
    })
})
