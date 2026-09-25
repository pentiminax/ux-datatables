import { beforeEach, describe, expect, it, vi } from 'vitest'
import { matchesClientFilters, registerFilterFeature } from '../filterFeature.js'

const reload = vi.fn()
const draw = vi.fn()
const saveState = vi.fn()
const register = vi.fn()
const searchExt: any[] = []

const DataTable: any = {
    feature: { register },
    ext: { search: searchExt },
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

    it('filters client-side rows based on applied filters in ext.search', () => {
        const filterBarMock: any = {
            collectValues: () => ({ status: 'active', name: 'alice' }),
            getDefinitions: () => [
                { name: 'status', type: 'select', options: { active: 'Active', inactive: 'Inactive' } },
                { name: 'name', type: 'text' },
            ],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'name', data: 'name' }, { name: 'status', data: 'status' }],
        }

        expect(searchExt.length).toBeGreaterThan(0)
        const filterFn = searchExt[0]

        // Matching row
        const matchingRow = { name: 'Alice Smith', status: 'active' }
        expect(filterFn(settings, ['Alice Smith', 'Active'], 0, matchingRow)).toBe(true)

        // Non-matching status
        const wrongStatusRow = { name: 'Alice Smith', status: 'inactive' }
        expect(filterFn(settings, ['Alice Smith', 'Inactive'], 1, wrongStatusRow)).toBe(false)

        // Non-matching text
        const wrongNameRow = { name: 'Bob Jones', status: 'active' }
        expect(filterFn(settings, ['Bob Jones', 'Active'], 2, wrongNameRow)).toBe(false)
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})


