import { beforeEach, describe, expect, it, vi } from 'vitest'
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
const registeredFeatureName = register.mock.calls[0]?.[0]
const callback = register.mock.calls[0]?.[1] as (settings: any, opts: any) => HTMLElement

describe('registerFilterFeature', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('registers a "filters" feature once and ignores repeat calls', () => {
        expect(registeredFeatureName).toBe('filters')
        const secondRegister = vi.fn()
        registerFilterFeature({ feature: { register: secondRegister } } as any)
        expect(secondRegister).not.toHaveBeenCalled()
    })

    it('renders the instance and wires reload to the table ajax for Ajax tables', () => {
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }

        const result = callback({ ajax: '/api/data' }, { instance })

        expect(result).toBe(node)
        expect(instance.render).toHaveBeenCalledTimes(1)

        const reloadCb = instance.render.mock.calls[0][0]
        reloadCb()
        expect(reload).toHaveBeenCalledWith(null, true)
    })

    it('does not render the filter bar for client-side tables with no Ajax source', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        const instance = { render: vi.fn() }

        const result = callback({}, { instance })

        expect(result).toBeInstanceOf(HTMLDivElement)
        expect(result.childNodes).toHaveLength(0)
        expect(instance.render).not.toHaveBeenCalled()
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('serverSide()'))
        warn.mockRestore()
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})
