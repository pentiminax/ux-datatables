import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { confirmBulkAction } from '../confirmModal.js'
import { BulkActionBar, hasBulkActions } from '../BulkActionBar.js'
import { FakeApi } from './fakeApi.js'

vi.mock('../confirmModal.js', () => ({
    confirmBulkAction: vi.fn().mockResolvedValue(true),
}))

vi.mock('../../functions/lucideIcons.js', () => ({
    renderLucideIcon: vi.fn(
        (name: string) => `<svg data-lucide="${name}" aria-hidden="true"></svg>`
    ),
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
            labels: { selected: '{count} selected', processed: '{count} processed' },
            ...bulkActions,
        },
    }
}

function build(overrides: Record<string, any> = {}): {
    bar: BulkActionBar
    api: FakeApi
    element: HTMLElement
    dispatch: ReturnType<typeof vi.fn>
} {
    const api = new FakeApi(
        [
            { id: '1', selected: false },
            { id: '2', selected: false },
        ],
        10
    )
    const dispatch = vi.fn()
    const bar = new BulkActionBar(payload(overrides), 'dt', dispatch)

    return { bar, api, element: bar.render(api), dispatch }
}

const actionButton = (element: HTMLElement): HTMLButtonElement =>
    element.querySelector<HTMLButtonElement>('[data-bulk-action="approve"]') as HTMLButtonElement

describe('BulkActionBar', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue(
                    new Response(JSON.stringify({ success: true, processed: 2, skipped: 1 }))
                )
        )
    })

    it('detects the payload key', () => {
        expect(hasBulkActions(payload())).toBe(true)
        expect(hasBulkActions({})).toBe(false)
        expect(hasBulkActions({ bulkActions: { actions: [] } })).toBe(false)
    })

    it('stays hidden until a row is selected', () => {
        const { api, element } = build()

        expect(element.hidden).toBe(true)

        api.emitSelection('select', [0])

        expect(element.hidden).toBe(false)
        expect(element.querySelector('.dt-bulk-bar__count')?.textContent).toBe('1 selected')
    })

    it('offers to select every matching row only while the page is a subset', () => {
        const { api, element } = build()
        const selectAll = element.querySelector<HTMLButtonElement>('.dt-bulk-bar__select-all')!

        api.emitSelection('select', [0])
        expect(selectAll.hidden).toBe(false)

        selectAll.click()
        expect(selectAll.hidden).toBe(true)
        expect(element.querySelector('.dt-bulk-bar__count')?.textContent).toBe('10 selected')
    })

    it('never offers a select all on a table limited to the current page', () => {
        const { api, element } = build({ bulkActions: { selectCurrentPageOnly: true } })

        api.emitSelection('select', [0])

        expect(
            element.querySelector<HTMLButtonElement>('.dt-bulk-bar__select-all')?.hidden
        ).toBe(true)
    })

    it('posts the selection, reports the counts and clears the rows', async () => {
        const { api, element, dispatch } = build()
        api.emitSelection('select', [0, 1])

        await actionButton(element).click()
        await vi.waitFor(() => expect(api.reloaded.length).toBe(1))

        const body = JSON.parse((fetch as any).mock.calls[0][1].body)
        expect(body).toMatchObject({ action: 'approve', ids: ['1', '2'], allMatching: false })
        expect(element.querySelector('.dt-bulk-bar__status')?.textContent).toContain('2 processed')
        expect(dispatch).toHaveBeenCalledWith('bulk:success', expect.anything())
        expect(element.hidden).toBe(true)
    })

    it('uses the table modal adapter for confirmations', async () => {
        const { api, element } = build({
            editModal: { adapter: 'custom-modal' },
            bulkActions: {
                actions: [{ name: 'approve', label: 'Approve', confirm: 'Continue?' }],
            },
        })
        api.emitSelection('select', [0])

        await actionButton(element).click()
        await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1))

        expect(confirmBulkAction).toHaveBeenCalledWith(
            expect.objectContaining({ adapterKey: 'custom-modal' })
        )
    })

    it('renders a Lucide bulk action icon', () => {
        const { element } = build({
            bulkActions: {
                actions: [{ name: 'approve', label: 'Approve', lucideIcon: 'check' }],
            },
        })

        expect(actionButton(element).querySelector('[data-lucide="check"]')).not.toBeNull()
    })

    it('sends the displayed request along with a select all', async () => {
        const { api, element } = build()
        api.emitSelection('select', [0])
        element.querySelector<HTMLButtonElement>('.dt-bulk-bar__select-all')!.click()

        await actionButton(element).click()
        await vi.waitFor(() => expect((fetch as any).mock.calls.length).toBe(1))

        expect(JSON.parse((fetch as any).mock.calls[0][1].body)).toMatchObject({
            allMatching: true,
            query: { draw: 1, search: { value: 'Beta' } },
        })
    })

    it('keeps a selection the user asked to preserve', async () => {
        const { api, element } = build({
            bulkActions: {
                actions: [{ name: 'approve', label: 'Approve', deselectAfterCompletion: false }],
            },
        })
        api.emitSelection('select', [0])

        await actionButton(element).click()
        await vi.waitFor(() => expect(api.reloaded.length).toBe(1))

        expect(element.hidden).toBe(false)
    })

    it('disables an action the server refused, and every action without mutations', () => {
        const denied = build({
            bulkActions: { actions: [{ name: 'approve', label: 'Approve', denied: true }] },
        })
        expect(actionButton(denied.element).disabled).toBe(true)

        const readOnly = build({ mutationsEnabled: false })
        expect(actionButton(readOnly.element).disabled).toBe(true)
    })

    it('does nothing without a selection', async () => {
        const { element } = build()

        await actionButton(element).click()

        expect(fetch).not.toHaveBeenCalled()
    })
})
