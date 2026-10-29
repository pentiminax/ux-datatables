// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import {
    aggregate,
    ensureSummaryFooter,
    hasSummaries,
    installColumnSummaries,
} from '../columnSummaries.js'

type Handler = (...args: any[]) => void

function fakeTable(tableElement: HTMLElement, rows: Record<number, unknown[]> = {}) {
    const handlers = new Map<string, Handler>()
    const table = {
        on: (event: string, handler: Handler) => handlers.set(event.split('.')[0], handler),
        column: (index: number) => ({
            footer: () => tableElement.querySelectorAll('tfoot th')[index] ?? null,
            data: () => ({ toArray: () => rows[index] ?? [] }),
        }),
    }

    return { table, handlers }
}

function setup(payload: Record<string, any>) {
    const element = document.createElement('table')
    element.innerHTML = '<thead><tr><th>Name</th><th>Total</th></tr></thead><tbody></tbody>'
    ensureSummaryFooter(element, payload)

    return element
}

const columns = [
    { name: 'name' },
    { name: 'total', summaries: [{ type: 'sum' }, { type: 'count', label: 'Orders' }] },
]

describe('columnSummaries', () => {
    it('detects summaries only when a column declares one', () => {
        expect(hasSummaries({ columns })).toBe(true)
        expect(hasSummaries({ columns: [{ name: 'a' }] })).toBe(false)
    })

    it('creates a footer with one cell per column', () => {
        const element = setup({ columns })

        expect(element.querySelectorAll('tfoot th[scope="col"]')).toHaveLength(2)
    })

    it('keeps an existing footer', () => {
        const element = document.createElement('table')
        element.innerHTML = '<tfoot><tr><td id="mine"></td><td></td></tr></tfoot>'

        expect(ensureSummaryFooter(element, { columns })).toBe(true)
        expect(element.querySelectorAll('tfoot')).toHaveLength(1)
        expect(element.querySelector('#mine')).not.toBeNull()
    })

    it('renders server summaries on xhr, with labels, leaving other cells empty', () => {
        const payload = { columns, serverSide: true, summaryLabels: { sum: 'Somme' } }
        const element = setup(payload)
        const { table, handlers } = fakeTable(element)

        installColumnSummaries(table, payload)
        handlers.get('xhr')?.({}, {}, { summaries: { total: { sum: 1234.5, count: 3 } } })

        const cells = element.querySelectorAll('tfoot th')
        expect(cells[0].textContent).toBe('')
        expect(cells[1].textContent).toContain('Somme')
        expect(cells[1].textContent).toContain('Orders')
        expect(cells[1].querySelectorAll('.dt-summary')).toHaveLength(2)
        expect(cells[1].textContent).toMatch(/1.?234/)
    })

    it('formats money cells with the column renderer', () => {
        const money = {
            name: 'total',
            summaries: [{ type: 'sum' }, { type: 'count' }],
            customOptions: { isMoney: true },
            render: (value: unknown) => `$${Number(value) / 100}`,
        }
        const payload = { columns: [{ name: 'name' }, money], serverSide: true }
        const element = setup(payload)
        const { table, handlers } = fakeTable(element)

        installColumnSummaries(table, payload)
        handlers.get('xhr')?.({}, {}, { summaries: { total: { sum: 12345, count: 2 } } })

        const values = [...element.querySelectorAll('.dt-summary__value')].map((n) => n.textContent)
        expect(values).toEqual(['$123.45', '2'])
    })

    it('escapes label and value', () => {
        const payload = {
            columns: [
                { name: 'a', summaries: [{ type: 'min', label: '<img src=x onerror=alert(1)>' }] },
            ],
            serverSide: true,
        }
        const element = setup(payload)
        const { table, handlers } = fakeTable(element)

        installColumnSummaries(table, payload)
        handlers.get('xhr')?.({}, {}, { summaries: { a: { min: '<b>x</b>' } } })

        expect(element.querySelector('img')).toBeNull()
        expect(element.querySelector('b')).toBeNull()
    })

    it('computes client-side summaries from the searched rows on draw', () => {
        const payload = { columns, serverSide: false }
        const element = setup(payload)
        const { table, handlers } = fakeTable(element, { 1: [10, '5', null, 5] })

        installColumnSummaries(table, payload)

        const values = [...element.querySelectorAll('.dt-summary__value')].map((n) => n.textContent)
        expect(values).toEqual(['20', '3'])
        expect(handlers.has('draw')).toBe(true)
    })

    it('clears the figures when the response carries no summaries', () => {
        const payload = { columns, serverSide: true }
        const element = setup(payload)
        const { table, handlers } = fakeTable(element)

        installColumnSummaries(table, payload)
        handlers.get('xhr')?.({}, {}, {})

        expect(
            [...element.querySelectorAll('.dt-summary__value')].map((n) => n.textContent)
        ).toEqual(['', ''])
    })

    it('aggregates like the server does', () => {
        expect(aggregate('sum', [1, '2', null, ''])).toBe(3)
        expect(aggregate('avg', [2, 4])).toBe(3)
        expect(aggregate('min', [3, 1, 2])).toBe(1)
        expect(aggregate('max', [3, 1, 2])).toBe(3)
        expect(aggregate('sum', [])).toBeNull()
    })

    it('counts every non-null value, empty strings included', () => {
        expect(aggregate('count', [1, null, '', 0, undefined])).toBe(3)
    })

    it('warns when an existing footer has the wrong number of cells', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        const element = document.createElement('table')
        element.innerHTML = '<tfoot><tr><td></td></tr></tfoot>'

        expect(ensureSummaryFooter(element, { columns })).toBe(false)
        expect(warn).toHaveBeenCalledOnce()
        warn.mockRestore()
    })
})
