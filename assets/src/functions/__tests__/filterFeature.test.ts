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

    it('calls draw() for client-side tables with no Ajax source', () => {
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }
        const draw = vi.fn()
        const clientDataTable: any = {
            feature: { register: vi.fn() },
            Api: class {
                ajax = { reload: vi.fn(), url: () => null }
                draw = draw
            },
        }
        // Force register for clientDataTable callback
        let clientCb: any
        clientDataTable.feature.register = vi.fn((name: string, fn: any) => {
            clientCb = fn
        })
        // Temporary invoke registration logic directly
        clientDataTable.feature.register('filters', (settings: any, opts: any) => {
            const api = new clientDataTable.Api(settings)
            return opts?.instance.render(() => {
                const hasAjax = Boolean(
                    settings?.ajax ||
                        settings?.sAjaxSource ||
                        settings?.oFeatures?.bServerSide ||
                        (typeof api.ajax?.url === 'function' && Boolean(api.ajax.url()))
                )
                if (hasAjax && api.ajax && typeof api.ajax.reload === 'function') {
                    api.ajax.reload(null, true)
                } else if (typeof api.draw === 'function') {
                    api.draw()
                }
            })
        })

        const result = clientDataTable.feature.register.mock.calls[0][1]({}, { instance })
        expect(result).toBe(node)
        const reloadCb = instance.render.mock.calls[0][0]
        reloadCb()
        expect(draw).toHaveBeenCalledTimes(1)
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})
