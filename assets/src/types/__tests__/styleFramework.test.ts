import { describe, expect, it } from 'vitest'
import { isStyleFramework, STYLE_FRAMEWORKS } from '../styleFramework.js'

describe('isStyleFramework', () => {
    it.each(STYLE_FRAMEWORKS.map(({ key }) => key))('accepts the declared key %s', (key) => {
        expect(isStyleFramework(key)).toBe(true)
    })

    it('rejects an unrecognized string', () => {
        expect(isStyleFramework('bootstrap5')).toBe(false)
    })

    it('rejects undefined, the value an unset payload option carries', () => {
        expect(isStyleFramework(undefined)).toBe(false)
    })

    it('rejects non-string values', () => {
        expect(isStyleFramework(5)).toBe(false)
        expect(isStyleFramework(null)).toBe(false)
        expect(isStyleFramework({})).toBe(false)
    })
})
