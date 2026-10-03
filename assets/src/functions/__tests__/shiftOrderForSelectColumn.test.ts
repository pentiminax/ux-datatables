import { describe, expect, it } from 'vitest'
import { shiftOrderForSelectColumn } from '../shiftOrderForSelectColumn.js'

describe('shiftOrderForSelectColumn', () => {
    it('shifts [index, direction] pairs', () => {
        expect(
            shiftOrderForSelectColumn([
                [0, 'asc'],
                [3, 'desc'],
            ])
        ).toEqual([
            [1, 'asc'],
            [4, 'desc'],
        ])
    })

    it('shifts {idx, dir} objects and keeps their other keys', () => {
        expect(shiftOrderForSelectColumn([{ idx: 2, dir: 'desc' }])).toEqual([
            { idx: 3, dir: 'desc' },
        ])
    })

    it('leaves name-based entries, an empty order and a missing order untouched', () => {
        expect(shiftOrderForSelectColumn([{ name: 'total', dir: 'asc' }])).toEqual([
            { name: 'total', dir: 'asc' },
        ])
        expect(shiftOrderForSelectColumn([])).toEqual([])
        expect(shiftOrderForSelectColumn(undefined)).toBeUndefined()
    })
})
