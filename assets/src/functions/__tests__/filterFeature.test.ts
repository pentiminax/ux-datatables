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

    it('skips non-displayed filter fields without hiding all rows', () => {
        const filterBarMock: any = {
            collectValues: () => ({ hiddenField: 'someValue' }),
            getDefinitions: () => [{ name: 'hiddenField', type: 'text' }],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'name', data: 'name' }],
        }

        const filterFn = searchExt[0]
        const row = { name: 'Alice' }
        expect(filterFn(settings, ['Alice'], 0, row)).toBe(true)
    })

    it('handles 0, "0", and false correctly in ternary filters', () => {
        const filterBarMock: any = {
            collectValues: () => ({ count: '1' }), // '1' = true / IS NOT NULL
            getDefinitions: () => [{ name: 'count', type: 'ternary' }],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'count', data: 'count' }],
        }

        const filterFn = searchExt[0]

        // 0, '0', false are NOT null, so IS NOT NULL (true) must keep them
        expect(filterFn(settings, ['0'], 0, { count: 0 })).toBe(true)
        expect(filterFn(settings, ['0'], 0, { count: '0' })).toBe(true)
        expect(filterFn(settings, ['false'], 0, { count: false })).toBe(true)

        // null and empty string are null, so IS NOT NULL must reject them
        expect(filterFn(settings, [''], 0, { count: null })).toBe(false)
        expect(filterFn(settings, [''], 0, { count: '' })).toBe(false)
    })

    it('compares date bounds safely across timezones', () => {
        const filterBarMock: any = {
            collectValues: () => ({ createdAt: { from: '2026-01-01', to: '2026-01-31' } }),
            getDefinitions: () => [{ name: 'createdAt', type: 'dateRange' }],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'createdAt', data: 'createdAt' }],
        }

        const filterFn = searchExt[0]

        expect(filterFn(settings, ['2026-01-31'], 0, { createdAt: '2026-01-31' })).toBe(true)
        expect(filterFn(settings, ['2026-01-15'], 0, { createdAt: '2026-01-15' })).toBe(true)
        expect(filterFn(settings, ['2026-02-01'], 0, { createdAt: '2026-02-01' })).toBe(false)
    })

    it('distinguishes duplicate labels with different raw values', () => {
        const filterBarMock: any = {
            collectValues: () => ({ role: '1' }),
            getDefinitions: () => [
                { name: 'role', type: 'select', options: { '1': 'Standard', '2': 'Standard' } },
            ],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'role', data: 'role' }],
        }

        const filterFn = searchExt[0]

        // Row has raw value '1' (selected)
        expect(filterFn(settings, ['Standard'], 0, { role: '1' })).toBe(true)

        // Row has raw value '2' (not selected, even though label is also 'Standard')
        expect(filterFn(settings, ['Standard'], 1, { role: '2' })).toBe(false)
    })

    it('preserves datetime timezone offsets during date range comparisons', () => {
        // 2026-01-31T23:30:00-05:00 is 2026-02-01T04:30:00Z in UTC
        const filterBarMock: any = {
            collectValues: () => ({ eventDate: { from: '2026-02-01' } }),
            getDefinitions: () => [{ name: 'eventDate', type: 'dateRange' }],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'eventDate', data: 'eventDate' }],
        }

        const filterFn = searchExt[0]

        // 2026-01-31T23:30:00-05:00 >= 2026-02-01T00:00:00Z -> true
        expect(filterFn(settings, ['2026-01-31 23:30'], 0, { eventDate: '2026-01-31T23:30:00-05:00' })).toBe(true)

        // 2026-01-31T10:00:00-05:00 is 2026-01-31T15:00:00Z < 2026-02-01T00:00:00Z -> false
        expect(filterFn(settings, ['2026-01-31 10:00'], 1, { eventDate: '2026-01-31T10:00:00-05:00' })).toBe(false)
    })

    it('considers nested non-empty rendered cell values as non-null in ternary filter', () => {
        const filterBarMock: any = {
            collectValues: () => ({ 'author.active': '1' }), // true state
            getDefinitions: () => [{ name: 'author.active', type: 'ternary' }],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'author.active', data: 'author.active' }],
        }

        const filterFn = searchExt[0]

        // Row has nested object author: { active: true }
        expect(filterFn(settings, ['Yes'], 0, { author: { active: true } })).toBe(true)

        // DOM row with renderedText 'Yes' even if rowData is empty object
        expect(filterFn(settings, ['Yes'], 1, {})).toBe(true)

        // Rendered cell is empty string -> null
        expect(filterFn(settings, [''], 2, {})).toBe(false)
    })

    it('rejects rows with empty array choices in multiselect filter', () => {
        const filterBarMock: any = {
            collectValues: () => ({ tags: ['php', 'symfony'] }),
            getDefinitions: () => [
                { name: 'tags', type: 'select', multiple: true, options: { php: 'PHP', symfony: 'Symfony' } },
            ],
        }

        const settings = {
            _uxFilterBar: filterBarMock,
            aoColumns: [{ name: 'tags', data: 'tags' }],
        }

        const filterFn = searchExt[0]

        // Row with empty tags array [] must NOT pass selection
        expect(filterFn(settings, [''], 0, { tags: [] })).toBe(false)

        // Row matching one tag
        expect(filterFn(settings, ['PHP'], 1, { tags: ['php'] })).toBe(true)
    })

    it('returns an empty node when no instance is provided', () => {
        const result = callback({}, null)
        expect(result).toBeInstanceOf(HTMLDivElement)
    })
})



