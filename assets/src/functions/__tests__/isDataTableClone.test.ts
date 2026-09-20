import { describe, expect, it } from 'vitest'
import { isDataTableClone } from '../isDataTableClone.js'

describe('isDataTableClone', () => {
    it('returns true for an element marked aria-hidden="true" (FixedHeader clone)', () => {
        const table = document.createElement('table')
        table.setAttribute('aria-hidden', 'true')

        expect(isDataTableClone(table)).toBe(true)
    })

    it('returns true for an element carrying the dtcr-cloned class (ColReorder drag clone)', () => {
        const table = document.createElement('table')
        table.classList.add('dtcr-cloned')

        expect(isDataTableClone(table)).toBe(true)
    })

    it('returns false for a live table with neither marker', () => {
        const table = document.createElement('table')

        expect(isDataTableClone(table)).toBe(false)
    })

    it('returns false when aria-hidden is present but not "true"', () => {
        const table = document.createElement('table')
        table.setAttribute('aria-hidden', 'false')

        expect(isDataTableClone(table)).toBe(false)
    })
})
