import { describe, expect, it, vi } from 'vitest'
import type { ColumnStyleAdapter } from '../ColumnStyleAdapter.js'
import { ColumnStyleAdapterRegistry } from '../ColumnStyleAdapterRegistry.js'

describe('ColumnStyleAdapterRegistry', () => {
    it('registers and returns factories', () => {
        const registry = new ColumnStyleAdapterRegistry()
        const adapter: ColumnStyleAdapter = {
            renderBadge: vi.fn(),
            renderSwitch: vi.fn(),
        }
        const factory = vi.fn().mockReturnValue(adapter)

        registry.register('custom', factory)

        expect(registry.get('custom')).toBe(factory)
        expect(registry.get('missing')).toBeNull()
    })

    it('overwrites an existing registration', () => {
        const registry = new ColumnStyleAdapterRegistry()
        const firstFactory = vi.fn()
        const secondFactory = vi.fn()

        registry.register('custom', firstFactory)
        registry.register('custom', secondFactory)

        expect(registry.get('custom')).toBe(secondFactory)
    })
})
