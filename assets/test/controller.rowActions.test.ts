import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

vi.mock('../src/functions/detectStyleFramework.js', () => ({
    detectStyleFramework: () => 'dt',
}))

vi.mock('../src/functions/deleteEntity.js', () => ({
    deleteEntity: vi.fn(async () => new Response(null, { status: 200 })),
}))

vi.mock('../src/functions/toggleBooleanValue.js', () => ({
    toggleBooleanValue: vi.fn(async () => new Response(null, { status: 200 })),
}))

vi.mock('../src/functions/fetchEditForm.js', () => ({
    fetchEditForm: vi.fn(async () => ({
        success: true,
        html: '<form></form>',
        response: new Response(null, { status: 200 }),
    })),
}))

vi.mock('../src/functions/submitEditForm.js', () => ({
    submitEditForm: vi.fn(async () => ({
        success: true,
        response: new Response(null, { status: 200 }),
    })),
}))

vi.mock('../src/modal/resolveModalAdapter.js', () => ({
    resolveModalAdapter: vi.fn(),
}))

vi.mock('../src/bulk/confirmModal.js', () => ({
    confirmAction: vi.fn(async () => true),
}))

import { confirmAction } from '../src/bulk/confirmModal.js'
import DatatableController from '../src/controller.js'
import { deleteEntity } from '../src/functions/deleteEntity.js'
import { fetchEditForm } from '../src/functions/fetchEditForm.js'
import { submitEditForm } from '../src/functions/submitEditForm.js'
import { resolveModalAdapter } from '../src/modal/resolveModalAdapter.js'
import { toggleBooleanValue } from '../src/functions/toggleBooleanValue.js'

const STATUS_SELECTOR = '[data-ux-datatables-row-action-status]'

describe('built-in row actions', () => {
    let application: Application
    let reload: ReturnType<typeof vi.fn>
    let payloads: Array<Record<string, any>>

    beforeEach(async () => {
        reload = vi.fn()
        payloads = []
        const initialized = new WeakSet<Element>()
        const built = new WeakMap<Element, object>()

        function MockDataTable(this: void, element: Element, payload: Record<string, any>) {
            payloads.push(payload)
            initialized.add(element)
            const container = document.createElement('div')
            container.className = 'dt-container'
            element.parentNode?.insertBefore(container, element)
            container.appendChild(element)

            const instance = { destroy: vi.fn(), on: vi.fn(), ajax: { reload } }
            built.set(element, instance)

            return instance
        }

        MockDataTable.feature = { register: vi.fn() }
        MockDataTable.isDataTable = (element: Element) => initialized.has(element)
        MockDataTable.Api = class {
            constructor(element: Element) {
                return built.get(element) as object
            }
        }

        vi.mocked(loadDataTableLibrary).mockResolvedValue(MockDataTable)

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('reloads the table and fires action:success when a delete succeeds', async () => {
        const table = await mountTable()
        const success = collectEvents(table, 'datatables:action:success')

        actionButton(table, 'DELETE').click()
        await settle()

        expect(reload).toHaveBeenCalledTimes(1)
        expect(success).toHaveLength(1)
        expect(success[0].detail).toMatchObject({ actionType: 'DELETE', id: '42' })
        expect(table.closest('.dt-container')?.querySelector(STATUS_SELECTOR)).toBeNull()
    })

    it('reports a denied delete through the event and an accessible message', async () => {
        vi.mocked(deleteEntity).mockResolvedValueOnce(new Response(null, { status: 403 }))
        const table = await mountTable({ actionLabels: { forbidden: 'Interdit.' } })
        const errors = collectEvents(table, 'datatables:action:error')

        actionButton(table, 'DELETE').click()
        await settle()

        expect(reload).not.toHaveBeenCalled()
        expect(errors).toHaveLength(1)
        expect(errors[0].detail).toMatchObject({ actionType: 'DELETE', id: '42' })
        expect(errors[0].detail.response.status).toBe(403)

        const region = table.closest('.dt-container')?.querySelector(STATUS_SELECTOR)
        expect(region?.textContent).toBe('Interdit.')
        expect(region?.getAttribute('aria-live')).toBe('polite')
    })

    it('falls back to the generic message for any other failure', async () => {
        vi.mocked(deleteEntity).mockResolvedValueOnce(new Response(null, { status: 409 }))
        const table = await mountTable()

        actionButton(table, 'DELETE').click()
        await settle()

        expect(table.closest('.dt-container')?.querySelector(STATUS_SELECTOR)?.textContent).toBe(
            'The action could not be completed.'
        )
    })

    it('lets a listener suppress the default message by canceling action:error', async () => {
        vi.mocked(deleteEntity).mockResolvedValueOnce(new Response(null, { status: 500 }))
        const table = await mountTable()
        table.addEventListener('datatables:action:error', (event) => event.preventDefault())

        actionButton(table, 'DELETE').click()
        await settle()

        expect(table.closest('.dt-container')?.querySelector(STATUS_SELECTOR)).toBeNull()
    })

    it('reports a request that throws', async () => {
        vi.mocked(deleteEntity).mockRejectedValueOnce(new TypeError('Failed to fetch'))
        const table = await mountTable()
        const errors = collectEvents(table, 'datatables:action:error')

        actionButton(table, 'DELETE').click()
        await settle()

        expect(errors).toHaveLength(1)
        expect(errors[0].detail.error).toBeInstanceOf(TypeError)
    })

    it('sends a single request when the button is clicked twice', async () => {
        let finish: (response: Response) => void = () => {}
        vi.mocked(deleteEntity).mockReturnValueOnce(
            new Promise<Response>((resolve) => {
                finish = resolve
            })
        )
        const table = await mountTable()
        const button = actionButton(table, 'DELETE')

        button.click()
        await settle()
        expect(button.getAttribute('aria-busy')).toBe('true')
        expect(button.disabled).toBe(true)

        button.disabled = false
        button.click()
        await settle()

        finish(new Response(null, { status: 200 }))
        await settle()

        expect(deleteEntity).toHaveBeenCalledTimes(1)
        expect(button.hasAttribute('aria-busy')).toBe(false)
        expect(button.disabled).toBe(false)
    })

    it('puts a failed boolean toggle back and fires action:error', async () => {
        vi.mocked(toggleBooleanValue).mockResolvedValueOnce(new Response(null, { status: 500 }))
        const table = await mountTable()
        const errors = collectEvents(table, 'datatables:action:error')

        const toggle = document.createElement('input')
        toggle.type = 'checkbox'
        toggle.className = 'boolean-switch-action'
        toggle.dataset.id = '42'
        toggle.dataset.field = 'active'
        toggle.checked = true
        table.appendChild(toggle)

        toggle.dispatchEvent(new Event('change', { bubbles: true }))
        await settle()

        expect(toggle.checked).toBe(false)
        expect(toggle.disabled).toBe(false)
        expect(errors).toHaveLength(1)
        expect(errors[0].detail).toMatchObject({ actionType: 'TOGGLE', id: '42' })
    })

    it('confirms through the modal adapter and sends no request when canceled', async () => {
        vi.mocked(confirmAction).mockResolvedValueOnce(false)
        const table = await mountTable({
            actionLabels: { confirm: 'Oui', cancel: 'Non' },
            editModal: { adapter: 'custom' },
        })

        actionButton(table, 'DELETE', 'Delete this row?').click()
        await settle()

        expect(confirmAction).toHaveBeenCalledWith(
            expect.objectContaining({
                message: 'Delete this row?',
                confirmLabel: 'Oui',
                cancelLabel: 'Non',
                adapterKey: 'custom',
            })
        )
        expect(deleteEntity).not.toHaveBeenCalled()
    })

    it('runs the action once the confirmation is accepted', async () => {
        const table = await mountTable()

        actionButton(table, 'DELETE', 'Delete this row?').click()
        await settle()

        expect(confirmAction).toHaveBeenCalledTimes(1)
        expect(deleteEntity).toHaveBeenCalledTimes(1)
    })

    it('opens one confirmation when the button is clicked twice before it answers', async () => {
        let answer: (value: boolean) => void = () => {}
        vi.mocked(confirmAction).mockReturnValueOnce(
            new Promise<boolean>((resolve) => {
                answer = resolve
            })
        )
        const table = await mountTable()
        const button = actionButton(table, 'DELETE', 'Delete this row?')

        button.click()
        button.click()
        await settle()

        answer(true)
        await settle()

        expect(confirmAction).toHaveBeenCalledTimes(1)
        expect(deleteEntity).toHaveBeenCalledTimes(1)
    })

    it('hands a formatted relative date to the text renderer alone', async () => {
        await mountTable({
            columns: [
                {
                    data: 'when',
                    type: 'date',
                    customOptions: { relative: true, formatted: true },
                },
            ],
        })

        const render = payloads[0].columns[0].render

        expect(render('2026-01-01', 'display')).toBe('2026-01-01')
        expect(render('<b>x</b>', 'display')).toBe('&lt;b&gt;x&lt;/b&gt;')
    })

    describe('inline edit', () => {
        let handlers: { onSubmit: (formData: Record<string, unknown>) => Promise<void> }
        const modal = {
            show: vi.fn(async (_html: string, given: typeof handlers) => {
                handlers = given
            }),
            hide: vi.fn(async () => {}),
            replaceBody: vi.fn(),
            isOpen: vi.fn(() => true),
        }

        beforeEach(() => {
            vi.mocked(resolveModalAdapter).mockResolvedValue(modal as never)
        })

        it('reports the load of the form only when it fails', async () => {
            const table = await mountTable()
            const success = collectEvents(table, 'datatables:action:success')

            actionButton(table, 'EDIT').click()
            await settle()

            expect(modal.show).toHaveBeenCalledTimes(1)
            expect(success).toHaveLength(0)

            vi.mocked(fetchEditForm).mockResolvedValueOnce({
                success: false,
                html: '',
                response: new Response(null, { status: 403 }),
            })
            const errors = collectEvents(table, 'datatables:action:error')

            actionButton(table, 'EDIT').click()
            await settle()

            expect(modal.show).toHaveBeenCalledTimes(1)
            expect(errors[0].detail.response.status).toBe(403)
            expect(
                table.closest('.dt-container')?.querySelector(STATUS_SELECTOR)?.textContent
            ).toBe('You are not allowed to do this.')
        })

        it('reports a saved edit and reloads the table', async () => {
            const table = await mountTable()
            const success = collectEvents(table, 'datatables:action:success')

            actionButton(table, 'EDIT').click()
            await settle()
            await handlers.onSubmit({ name: 'Ada' })
            await settle()

            expect(success).toHaveLength(1)
            expect(success[0].detail).toMatchObject({ actionType: 'EDIT', id: '42' })
            expect(modal.hide).toHaveBeenCalled()
            expect(reload).toHaveBeenCalledTimes(1)
        })

        it('reports a failed save and keeps the modal open on the returned form', async () => {
            vi.mocked(submitEditForm).mockResolvedValueOnce({
                success: false,
                html: '<p>Invalid</p>',
                response: new Response(null, { status: 422 }),
            })
            const table = await mountTable()
            const errors = collectEvents(table, 'datatables:action:error')

            actionButton(table, 'EDIT').click()
            await settle()
            await handlers.onSubmit({ name: '' })
            await settle()

            expect(errors).toHaveLength(1)
            expect(errors[0].detail.response.status).toBe(422)
            expect(modal.replaceBody).toHaveBeenCalledWith('<p>Invalid</p>')
            expect(modal.hide).not.toHaveBeenCalled()
        })
    })

    it('keeps the native confirm for a link the controller does not run', async () => {
        const nativeConfirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
        const table = await mountTable()
        const link = document.createElement('a')
        link.href = '#'
        link.setAttribute('data-action-type', 'URL')
        link.setAttribute('data-confirm', 'Leave?')
        table.appendChild(link)

        link.click()
        await settle()

        expect(nativeConfirm).toHaveBeenCalledWith('Leave?')
        expect(confirmAction).not.toHaveBeenCalled()
        nativeConfirm.mockRestore()
    })
})

async function mountTable(extraView: Record<string, unknown> = {}): Promise<HTMLTableElement> {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute(
        'data-datatable-view-value',
        JSON.stringify({
            columns: [{ data: 'name', title: 'Name' }],
            dataTable: 'token',
            mutationsEnabled: true,
            ...extraView,
        })
    )
    document.body.appendChild(table)
    await settle()

    return table
}

function actionButton(
    table: HTMLTableElement,
    type: string,
    confirmMessage?: string
): HTMLButtonElement {
    const button = document.createElement('button')
    button.setAttribute('data-action-type', type)
    button.setAttribute('data-id', '42')
    if (confirmMessage) {
        button.setAttribute('data-confirm', confirmMessage)
    }
    table.appendChild(button)

    return button
}

function collectEvents(element: Element, type: string): CustomEvent[] {
    const events: CustomEvent[] = []
    element.addEventListener(type, (event) => events.push(event as CustomEvent))

    return events
}

async function settle(): Promise<void> {
    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}
