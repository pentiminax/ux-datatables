import { describe, expect, it } from 'vitest'
import { applyFilterLayout } from '../filterLayout.js'
import type { FilterBar } from '../filters.js'

const instance = {} as FilterBar

function entry() {
    return { filters: { instance } }
}

describe('applyFilterLayout', () => {
    it('appends the feature next to search when no layout is set', () => {
        const payload: Record<string, any> = {}
        applyFilterLayout(payload, instance)
        expect(payload.layout).toEqual({ topEnd: ['search', entry()] })
    })

    it('appends to an existing topEnd array, preserving entries', () => {
        const payload: Record<string, any> = { layout: { topEnd: ['search', 'pageLength'] } }
        applyFilterLayout(payload, instance)
        expect(payload.layout.topEnd).toEqual(['search', 'pageLength', entry()])
    })

    it('wraps a scalar topEnd into an array', () => {
        const payload: Record<string, any> = { layout: { topEnd: 'search' } }
        applyFilterLayout(payload, instance)
        expect(payload.layout.topEnd).toEqual(['search', entry()])
    })

    it('does not touch other layout slots when appending', () => {
        const payload: Record<string, any> = {
            layout: { topStart: 'pageLength', bottomEnd: 'paging' },
        }
        applyFilterLayout(payload, instance)
        expect(payload.layout.topStart).toBe('pageLength')
        expect(payload.layout.bottomEnd).toBe('paging')
        expect(payload.layout.topEnd).toEqual(['search', entry()])
    })

    it('replaces a "filters" string marker placed via PHP, keeping its position', () => {
        const payload: Record<string, any> = { layout: { topEnd: ['filters', 'search'] } }
        applyFilterLayout(payload, instance)
        expect(payload.layout.topEnd).toEqual([entry(), 'search'])
    })

    it('replaces a standalone "filters" marker on a slot', () => {
        const payload: Record<string, any> = { layout: { topStart: 'filters' } }
        applyFilterLayout(payload, instance)
        expect(payload.layout.topStart).toEqual(entry())
        // no extra append happened
        expect(payload.layout.topEnd).toBeUndefined()
    })
})
