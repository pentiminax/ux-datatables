import { describe, expect, it } from 'vitest'
import { isFixedHeaderClone } from '../isFixedHeaderClone.js'

describe('isFixedHeaderClone', () => {
    it('returns true for an element marked aria-hidden="true"', () => {
        const table = document.createElement('table')
        table.setAttribute('aria-hidden', 'true')

        expect(isFixedHeaderClone(table)).toBe(true)
    })

    it('returns false for a live table without aria-hidden', () => {
        const table = document.createElement('table')

        expect(isFixedHeaderClone(table)).toBe(false)
    })

    it('returns false when aria-hidden is present but not "true"', () => {
        const table = document.createElement('table')
        table.setAttribute('aria-hidden', 'false')

        expect(isFixedHeaderClone(table)).toBe(false)
    })
})
