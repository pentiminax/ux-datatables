import { beforeEach, describe, expect, it, vi } from 'vitest'
import { registerFilterFeature } from '../filterFeature.js'

const reload = vi.fn()
const draw = vi.fn()
const saveState = vi.fn()
const register = vi.fn()

const DataTable: any = {
    feature: { register },
    Api: class {
        ajax: any
        draw = draw
        state = { save: saveState }

        constructor(public settings?: any) {
            this.ajax = {
                reload,
                url: () => settings?.ajaxUrl ?? (settings?.ajax ? 'https://example.com/ajax' : null),
            }
        }
    },
}

// Registration is a module-level singleton, so register exactly once and reuse
// the captured callback across the behavioural assertions below.
registerFilterFeature(DataTable)
const callback = register.mock.calls[0][1] as (settings: any, opts: any) => HTMLElement

describe('registerFilterFeature', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('registers a "filters" feature once and ignores repeat calls', () => {
        registerFilterFeature({ feature: { register: vi.fn() } } as any)
        expect(register).toHaveBeenCalledTimes(0) // cleared by beforeEach, but originally called once
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
        expect(draw).not.toHaveBeenCalled()
        expect(saveState).toHaveBeenCalledTimes(1)
    })

    it('calls draw() for client-side tables with no Ajax source', () => {
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }

        const result = callback({}, { instance })

        expect(result).toBe(node)
        expect(instance.render).toHaveBeenCalledTimes(1)

        const reloadCb = instance.render.mock.calls[0][0]
        reloadCb()
        expect(draw).toHaveBeenCalledTimes(1)
        expect(reload).not.toHaveBeenCalled()
        expect(saveState).toHaveBeenCalledTimes(1)
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})

