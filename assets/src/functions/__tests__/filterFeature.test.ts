import { describe, expect, it, vi } from 'vitest'
import { registerFilterFeature } from '../filterFeature.js'

const reload = vi.fn()
const register = vi.fn()
const DataTable: any = {
    feature: { register },
    Api: class {
        ajax = { reload }
    },
}

// Registration is a module-level singleton, so register exactly once and reuse
// the captured callback across the behavioural assertions below.
registerFilterFeature(DataTable)
const callback = register.mock.calls[0][1] as (settings: any, opts: any) => HTMLElement

describe('registerFilterFeature', () => {
    it('registers a "filters" feature once and ignores repeat calls', () => {
        registerFilterFeature({ feature: { register: vi.fn() } } as any)
        expect(register).toHaveBeenCalledTimes(1)
        expect(register.mock.calls[0][0]).toBe('filters')
    })

    it('renders the instance and wires reload to the table ajax', () => {
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }

        const result = callback({ settings: true }, { instance })

        expect(result).toBe(node)
        expect(instance.render).toHaveBeenCalledTimes(1)

        const reloadCb = instance.render.mock.calls[0][0]
        reloadCb()
        expect(reload).toHaveBeenCalledWith(null, true)
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})
