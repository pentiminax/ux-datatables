import { describe, expect, it, vi } from 'vitest'
import { ModalAdapterRegistry } from '../ModalAdapterRegistry.js'
import type { ModalAdapter } from '../ModalAdapter.js'

describe('ModalAdapterRegistry', () => {
    it('registers and returns factories', async () => {
        const registry = new ModalAdapterRegistry()
        const adapter: ModalAdapter = {
            show: vi.fn(),
            replaceBody: vi.fn(),
            hide: vi.fn(),
            isOpen: vi.fn(),
        }
        const factory = vi.fn().mockResolvedValue(adapter)

        registry.register('custom', factory)

        expect(registry.get('custom')).toBe(factory)
        expect(registry.get('missing')).toBeNull()
    })

    it('overwrites an existing registration', async () => {
        const registry = new ModalAdapterRegistry()
        const firstFactory = vi.fn()
        const secondFactory = vi.fn()

        registry.register('custom', firstFactory)
        registry.register('custom', secondFactory)

        expect(registry.get('custom')).toBe(secondFactory)
    })
})
