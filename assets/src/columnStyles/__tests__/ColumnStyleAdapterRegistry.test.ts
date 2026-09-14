import { describe, expect, it, vi } from 'vitest'
import { BootstrapColumnStyleAdapter } from '../BootstrapColumnStyleAdapter.js'
import type { ColumnStyleAdapter } from '../ColumnStyleAdapter.js'
import { ColumnStyleAdapterRegistry, columnStyleAdapters } from '../ColumnStyleAdapterRegistry.js'
import { TailwindColumnStyleAdapter } from '../TailwindColumnStyleAdapter.js'
import { TailwindThemeColumnStyleAdapter } from '../TailwindThemeColumnStyleAdapter.js'

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

describe('columnStyleAdapters', () => {
    it.each([
        ['bs', BootstrapColumnStyleAdapter],
        ['bs4', BootstrapColumnStyleAdapter],
        ['bs5', BootstrapColumnStyleAdapter],
        ['dt', TailwindColumnStyleAdapter],
        ['tailwind', TailwindThemeColumnStyleAdapter],
    ])('builds the %s adapter', (key, expected) => {
        const factory = columnStyleAdapters.get(key)

        expect(factory).not.toBeNull()
        expect(factory?.()).toBeInstanceOf(expected)
    })
})
