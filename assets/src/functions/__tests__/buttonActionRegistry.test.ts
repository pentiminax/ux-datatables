import { describe, expect, it } from 'vitest'
import { ButtonActionRegistry } from '../buttonActionRegistry.js'

describe('ButtonActionRegistry', () => {
    it('resolves a registered action by name', () => {
        const registry = new ButtonActionRegistry()
        const action = () => {}

        expect(registry.register('restoreOrder', action)).toBe(registry)
        expect(registry.get('restoreOrder')).toBe(action)
    })

    it('returns null for an unregistered name', () => {
        const registry = new ButtonActionRegistry()

        expect(registry.get('missing')).toBeNull()
    })

    it('overwrites a previously registered action under the same name', () => {
        const registry = new ButtonActionRegistry()
        const first = () => {}
        const second = () => {}

        registry.register('restoreOrder', first)
        registry.register('restoreOrder', second)

        expect(registry.get('restoreOrder')).toBe(second)
    })
})
