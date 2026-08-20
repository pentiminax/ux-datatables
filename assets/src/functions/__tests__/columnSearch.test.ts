import { describe, expect, it, vi } from 'vitest'
import { applyTfootColumnSearch, hasTfootSearch } from '../columnSearch.js'

// ---------------------------------------------------------------------------
// Minimal DataTable.Api mock
// ---------------------------------------------------------------------------

function makeApi(tableEl: HTMLTableElement) {
    const searchFn = vi.fn().mockReturnThis()
    const drawFn = vi.fn().mockReturnThis()

    const columnFn = vi.fn().mockReturnValue({
        search: searchFn,
        draw: drawFn,
    })

    class MockApi {
        table() {
            return { node: () => tableEl }
        }
        column(...args: any[]) {
            return columnFn(...args)
        }
    }

    return { MockApi, columnFn, searchFn, drawFn }
}

function makeDataTable(tableEl: HTMLTableElement) {
    const { MockApi, columnFn, searchFn, drawFn } = makeApi(tableEl)
    const DataTable = { Api: MockApi }
    return { DataTable, columnFn, searchFn, drawFn }
}

// Build a payload with the given columns and call applyTfootColumnSearch.
// Returns the payload (with initComplete set) and the table element.
function setup(
    columnDefs: Array<{ searchable: boolean; title?: string }>,
    framework: 'dt' | 'bs5' = 'dt',
) {
    const tableEl = document.createElement('table')
    const payload: Record<string, any> = { tfootSearch: true, columns: columnDefs }
    const { DataTable, columnFn, searchFn, drawFn } = makeDataTable(tableEl)
    applyTfootColumnSearch(payload, framework, DataTable)
    // Simulate DataTables calling initComplete
    payload.initComplete.call(null, {}, null)
    return { payload, tableEl, columnFn, searchFn, drawFn }
}

// ---------------------------------------------------------------------------
// hasTfootSearch
// ---------------------------------------------------------------------------

describe('hasTfootSearch', () => {
    it('returns true only when tfootSearch is exactly true', () => {
        expect(hasTfootSearch({ tfootSearch: true })).toBe(true)
        expect(hasTfootSearch({ tfootSearch: false })).toBe(false)
        expect(hasTfootSearch({ tfootSearch: 1 })).toBe(false)
        expect(hasTfootSearch({})).toBe(false)
    })
})

// ---------------------------------------------------------------------------
// applyTfootColumnSearch — DOM structure
// ---------------------------------------------------------------------------

describe('applyTfootColumnSearch — DOM structure', () => {
    it('creates a <tfoot> with a <tr> when none exists', () => {
        const { tableEl } = setup([{ searchable: true, title: 'Name' }])
        expect(tableEl.querySelector('tfoot')).not.toBeNull()
        expect(tableEl.querySelector('tfoot tr')).not.toBeNull()
    })

    it('appends to an existing <tfoot> instead of creating a second one', () => {
        const tableEl = document.createElement('table')
        const existingTfoot = document.createElement('tfoot')
        tableEl.appendChild(existingTfoot)

        const payload: Record<string, any> = {
            tfootSearch: true,
            columns: [{ searchable: true, title: 'Name' }],
        }
        const { DataTable } = makeDataTable(tableEl)
        applyTfootColumnSearch(payload, 'dt', DataTable)
        payload.initComplete.call(null, {}, null)

        expect(tableEl.querySelectorAll('tfoot')).toHaveLength(1)
    })

    it('renders one <th> per column', () => {
        const { tableEl } = setup([
            { searchable: true, title: 'Name' },
            { searchable: false },
            { searchable: true, title: 'Email' },
        ])
        const ths = tableEl.querySelectorAll('tfoot tr th')
        expect(ths).toHaveLength(3)
    })

    it('renders a search input inside searchable columns', () => {
        const { tableEl } = setup([{ searchable: true, title: 'Name' }])
        const input = tableEl.querySelector('tfoot tr th input[type="search"]')
        expect(input).not.toBeNull()
    })

    it('leaves non-searchable column cells empty', () => {
        const { tableEl } = setup([{ searchable: false }])
        const th = tableEl.querySelector('tfoot tr th')!
        expect(th.children).toHaveLength(0)
    })

    it('sets placeholder and aria-label from the column title', () => {
        const { tableEl } = setup([{ searchable: true, title: 'Email address' }])
        const input = tableEl.querySelector('tfoot tr th input') as HTMLInputElement
        expect(input.placeholder).toBe('Email address')
        expect(input.getAttribute('aria-label')).toBe('Email address')
    })

    it('uses an empty placeholder when the column has no title', () => {
        const { tableEl } = setup([{ searchable: true }])
        const input = tableEl.querySelector('tfoot tr th input') as HTMLInputElement
        expect(input.placeholder).toBe('')
    })
})

// ---------------------------------------------------------------------------
// applyTfootColumnSearch — CSS classes
// ---------------------------------------------------------------------------

describe('applyTfootColumnSearch — CSS classes', () => {
    it('applies dt-filter-input class for the default dt framework', () => {
        const { tableEl } = setup([{ searchable: true, title: 'Name' }], 'dt')
        const input = tableEl.querySelector('tfoot tr th input') as HTMLInputElement
        expect(input.className).toBe('dt-filter-input')
    })

    it('applies form-control class for the bs5 framework', () => {
        const { tableEl } = setup([{ searchable: true, title: 'Name' }], 'bs5')
        const input = tableEl.querySelector('tfoot tr th input') as HTMLInputElement
        expect(input.className).toBe('form-control')
    })
})

// ---------------------------------------------------------------------------
// applyTfootColumnSearch — search behaviour
// ---------------------------------------------------------------------------

describe('applyTfootColumnSearch — search behaviour', () => {
    it('calls column(index).search(value).draw(false) on input', () => {
        const { tableEl, columnFn, searchFn, drawFn } = setup([
            { searchable: false },
            { searchable: true, title: 'Email' },
        ])

        const input = tableEl.querySelector('tfoot tr th:nth-child(2) input') as HTMLInputElement
        input.value = 'foo@example.com'
        input.dispatchEvent(new Event('input'))

        // Column index 1 (second column), with { search: 'applied' } scope
        expect(columnFn).toHaveBeenCalledWith(1, { search: 'applied' })
        expect(searchFn).toHaveBeenCalledWith('foo@example.com')
        expect(drawFn).toHaveBeenCalledWith(false)
    })

    it('searches with an empty string when the input is cleared', () => {
        const { tableEl, searchFn } = setup([{ searchable: true, title: 'Name' }])

        const input = tableEl.querySelector('tfoot tr th input') as HTMLInputElement
        input.value = ''
        input.dispatchEvent(new Event('input'))

        expect(searchFn).toHaveBeenCalledWith('')
    })
})

// ---------------------------------------------------------------------------
// applyTfootColumnSearch — initComplete chaining
// ---------------------------------------------------------------------------

describe('applyTfootColumnSearch — initComplete chaining', () => {
    it('calls an existing initComplete before building the tfoot', () => {
        const calls: string[] = []

        const tableEl = document.createElement('table')
        const payload: Record<string, any> = {
            tfootSearch: true,
            columns: [{ searchable: true, title: 'Name' }],
            initComplete: () => calls.push('prior'),
        }

        const { DataTable } = makeDataTable(tableEl)
        applyTfootColumnSearch(payload, 'dt', DataTable)

        // Intercept to record tfoot creation after chained call
        const wrappedInitComplete = payload.initComplete
        payload.initComplete = function (settings: any, data: any) {
            wrappedInitComplete.call(this, settings, data)
            calls.push('tfoot')
        }

        payload.initComplete.call(null, {}, null)

        expect(calls).toEqual(['prior', 'tfoot'])
    })

    it('does not throw when no prior initComplete exists', () => {
        const tableEl = document.createElement('table')
        const payload: Record<string, any> = {
            tfootSearch: true,
            columns: [{ searchable: true }],
        }
        const { DataTable } = makeDataTable(tableEl)
        applyTfootColumnSearch(payload, 'dt', DataTable)
        expect(() => payload.initComplete.call(null, {}, null)).not.toThrow()
    })
})
