import { describe, expect, it, vi } from 'vitest'
import { packageLoaders } from '../../functions/packageLoaders.js'
import { isStyleFramework, registeredStyleFramework, STYLE_FRAMEWORKS } from '../styleFramework.js'

describe('isStyleFramework', () => {
    it.each(STYLE_FRAMEWORKS)('accepts the declared key %s', (key) => {
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

describe('registeredStyleFramework', () => {
    it('is the style of the registered loaders, and dt when none names one', async () => {
        vi.resetModules()
        const { packageLoaders: loaders } = await import('../../functions/packageLoaders.js')
        const { registeredStyleFramework: resolve } = await import('../styleFramework.js')

        expect(resolve()).toBe('dt')

        loaders.register('dataTables', async () => ({}), 'bs5')
        expect(resolve()).toBe('bs5')

        loaders.register('dataTables', async () => ({}), 'dt')
        expect(() => resolve()).toThrow('Several styles are registered (bs5, dt)')
    })

    it('reads the shared registry', () => {
        expect(typeof registeredStyleFramework).toBe('function')
        expect(packageLoaders).toBeDefined()
    })
})
