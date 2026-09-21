import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadLucideIcons } from '../../functions/lucideIcons.js'
import { BulkActionBar, hasBulkActions } from '../BulkActionBar.js'
import { confirmBulkAction } from '../confirmModal.js'
import { FakeApi } from './fakeApi.js'

vi.mock('../confirmModal.js', () => ({
    confirmBulkAction: vi.fn(async () => true),
}))

function payload(overrides: Record<string, any> = {}): Record<string, any> {
    const { bulkActions, ...rest } = overrides

    return {
        dataTable: 'signed-token',
        csrfToken: 'csrf-value',
        mutationsEnabled: true,
        ...rest,
        bulkActions: {
            actions: [{ name: 'approve', label: 'Approve' }],
            selectCurrentPageOnly: false,
            url: '/datatables/ajax/bulk',
            labels: {
                trigger: 'Bulk actions',
                selected: '{count} records selected',
                selectAllMatching: 'Select all {count}',
                clear: 'Deselect all',
                processed: '{count} processed',
            },
            ...bulkActions,
        },
    }
}

interface Harness {
    bar: BulkActionBar
    api: FakeApi
    wrapper: HTMLElement
    row: HTMLElement
    tableRow: HTMLElement
    dispatch: ReturnType<typeof vi.fn>
}

/** The trigger lives in a DataTables layout row; the selection band mounts above the table row. */
function build(overrides: Record<string, any> = {}): Harness {
    const api = new FakeApi(
        [
            { id: '1', selected: false },
            { id: '2', selected: false },
        ],
        10
    )
    const dispatch = vi.fn()
    const bar = new BulkActionBar(payload(overrides), 'dt', dispatch)

    const container = document.createElement('div')
    container.className = 'dt-container'
    const row = document.createElement('div')
    row.className = 'dt-layout-row'
    const tableRow = document.createElement('div')
    tableRow.className = 'dt-layout-row dt-layout-table'
    container.append(row, tableRow)
    document.body.appendChild(container)

    row.appendChild(bar.render(api))

    return {
        bar,
        api,
        wrapper: row.querySelector('.dt-bulk') as HTMLElement,
        row,
        tableRow,
        dispatch,
    }
}

const trigger = (h: Harness): HTMLButtonElement =>
    h.wrapper.querySelector('.dt-bulk-trigger') as HTMLButtonElement
const menu = (h: Harness): HTMLElement => h.wrapper.querySelector('.dt-bulk-menu') as HTMLElement
const item = (h: Harness, name = 'approve'): HTMLButtonElement =>
    h.wrapper.querySelector(`[data-bulk-action="${name}"]`) as HTMLButtonElement
const summary = (): HTMLElement => document.querySelector('.dt-bulk-summary') as HTMLElement

describe('BulkActionBar', () => {
    beforeAll(async () => {
        await loadLucideIcons()
    })

    beforeEach(() => {
        document.body.innerHTML = ''
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue(
                    new Response(JSON.stringify({ success: true, processed: 2, skipped: 1 }))
                )
        )
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('detects the payload key', () => {
        expect(hasBulkActions(payload())).toBe(true)
        expect(hasBulkActions({})).toBe(false)
        expect(hasBulkActions({ bulkActions: { actions: [] } })).toBe(false)
    })

    it('renders a disabled trigger until a row is selected', () => {
        const h = build()

        expect(trigger(h).textContent).toContain('Bulk actions')
        expect(trigger(h).disabled).toBe(true)

        h.api.emitSelection('select', [0])

        expect(trigger(h).disabled).toBe(false)
    })

    it('lists one menu entry per action and opens on the trigger', () => {
        const h = build({
            bulkActions: {
                actions: [
                    { name: 'approve', label: 'Approve' },
                    { name: 'archive', label: 'Archive' },
                ],
            },
        })
        h.api.emitSelection('select', [0])

        expect(menu(h).querySelectorAll('[role="menuitem"]')).toHaveLength(2)
        expect(menu(h).hidden).toBe(true)

        trigger(h).click()

        expect(menu(h).hidden).toBe(false)
        expect(trigger(h).getAttribute('aria-expanded')).toBe('true')

        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))

        expect(menu(h).hidden).toBe(true)
    })

    it('closes the menu when the selection drops back to nothing', () => {
        const h = build()
        h.api.emitSelection('select', [0])
        trigger(h).click()

        h.api.emitSelection('deselect', [0])

        expect(menu(h).hidden).toBe(true)
        expect(trigger(h).disabled).toBe(true)
    })

    it('renders the lucide icon a bulk action declares', () => {
        const h = build({
            bulkActions: {
                actions: [{ name: 'delete', label: 'Delete', lucideIcon: 'trash' }],
            },
        })

        const icon = item(h, 'delete').querySelector('svg')

        expect(icon).not.toBeNull()
        expect(icon?.getAttribute('aria-hidden')).toBe('true')
        expect(item(h, 'delete').textContent).toBe('Delete')
    })

    it('mounts the selection band right above the table row', async () => {
        const h = build()

        // DataTables inserts the trigger after the feature returns, so the band lands on the
        // microtask render() queues.
        await Promise.resolve()

        expect(h.row.nextElementSibling).toBe(summary())
        expect(summary().nextElementSibling).toBe(h.tableRow)
        expect(summary().classList.contains('dt-bulk-summary--empty')).toBe(true)

        h.api.emitSelection('select', [0, 1])

        expect(summary().classList.contains('dt-bulk-summary--empty')).toBe(false)
        expect(summary().querySelector('.dt-bulk-summary__count')?.textContent).toBe(
            '2 records selected'
        )
    })

    it('offers to select every matching row only while the page is a subset', () => {
        const h = build()
        const selectAll = () =>
            summary().querySelector('.dt-bulk-summary__select-all') as HTMLButtonElement

        h.api.emitSelection('select', [0])
        expect(selectAll().hidden).toBe(false)
        expect(selectAll().textContent).toBe('Select all 10')

        selectAll().click()

        expect(selectAll().hidden).toBe(true)
        expect(summary().querySelector('.dt-bulk-summary__count')?.textContent).toContain('10')
    })

    it('never offers a select all on a table limited to the current page', () => {
        const h = build({ bulkActions: { selectCurrentPageOnly: true } })
        h.api.emitSelection('select', [0])

        expect(
            summary().querySelector<HTMLButtonElement>('.dt-bulk-summary__select-all')?.hidden
        ).toBe(true)
    })

    it('clears the selection from the band', () => {
        const h = build()
        h.api.emitSelection('select', [0, 1])

        summary().querySelector<HTMLButtonElement>('.dt-bulk-summary__clear')?.click()

        expect(h.api.rows({ selected: true }).ids().toArray()).toEqual([])
        expect(trigger(h).disabled).toBe(true)
    })

    it('posts the selection, closes the menu and reports the counts', async () => {
        const h = build()
        h.api.emitSelection('select', [0, 1])
        trigger(h).click()

        item(h).click()
        await vi.waitFor(() => expect(h.api.reloaded.length).toBe(1))

        expect(menu(h).hidden).toBe(true)
        expect(JSON.parse((fetch as any).mock.calls[0][1].body)).toMatchObject({
            action: 'approve',
            ids: ['1', '2'],
            allMatching: false,
        })
        expect(summary().querySelector('.dt-bulk-summary__count')?.textContent).toContain(
            '2 processed'
        )
        expect(h.dispatch).toHaveBeenCalledWith('bulk:success', expect.anything())
    })

    it('sends the displayed request along with a select all', async () => {
        const h = build()
        h.api.emitSelection('select', [0])
        summary().querySelector<HTMLButtonElement>('.dt-bulk-summary__select-all')?.click()

        item(h).click()
        await vi.waitFor(() => expect((fetch as any).mock.calls.length).toBe(1))

        expect(JSON.parse((fetch as any).mock.calls[0][1].body)).toMatchObject({
            allMatching: true,
            query: { draw: 1, search: { value: 'Beta' } },
        })
    })

    it('keeps a selection the user asked to preserve', async () => {
        const h = build({
            bulkActions: {
                actions: [{ name: 'approve', label: 'Approve', deselectAfterCompletion: false }],
            },
        })
        h.api.emitSelection('select', [0])

        item(h).click()
        await vi.waitFor(() => expect(h.api.reloaded.length).toBe(1))

        expect(h.api.rows({ selected: true }).ids().toArray()).toEqual(['1'])
    })

    it('disables an entry the server refused, and every entry without mutations', () => {
        const denied = build({
            bulkActions: { actions: [{ name: 'approve', label: 'Approve', denied: true }] },
        })
        expect(item(denied).disabled).toBe(true)

        document.body.innerHTML = ''
        const readOnly = build({ mutationsEnabled: false })
        expect(item(readOnly).disabled).toBe(true)
        expect(trigger(readOnly).disabled).toBe(true)
    })

    it('confirms through the modal adapter the table was configured with', async () => {
        const h = build({
            editModal: { adapter: 'bs5' },
            bulkActions: {
                actions: [{ name: 'approve', label: 'Approve', confirm: 'Approve {count} rows?' }],
            },
        })
        h.api.emitSelection('select', [0])

        item(h).click()

        await vi.waitFor(() => expect(confirmBulkAction).toHaveBeenCalled())

        expect(vi.mocked(confirmBulkAction).mock.calls[0][0]).toMatchObject({
            message: 'Approve 1 rows?',
            adapterKey: 'bs5',
        })
    })

    it('does nothing without a selection', async () => {
        const h = build()

        item(h).click()

        expect(fetch).not.toHaveBeenCalled()
    })
})
