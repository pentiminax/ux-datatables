import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DialogModalAdapter } from '../DialogModalAdapter.js'

describe('DialogModalAdapter', () => {
    beforeEach(() => {
        HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) {
            Object.defineProperty(this, 'open', { configurable: true, value: true })
        })
        HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) {
            Object.defineProperty(this, 'open', { configurable: true, value: false })
            this.dispatchEvent(new Event('close'))
        })
    })

    afterEach(() => {
        document.body.innerHTML = ''
        vi.restoreAllMocks()
    })

    it('renders the dialog, submits the form, replaces the body, and cleans up on hide', async () => {
        const adapter = new DialogModalAdapter()
        const onSubmit = vi.fn().mockResolvedValue(undefined)

        await adapter.show(
            `
                <dialog data-ux-datatables-modal>
                    <div data-ux-datatables-modal-body>
                        <form id="ux-datatables-edit-form">
                            <input name="product[name]" value="Desk" />
                        </form>
                    </div>
                    <button type="button" data-ux-datatables-submit>Save</button>
                </dialog>
            `,
            { onSubmit }
        )

        expect(adapter.isOpen()).toBe(true)
        expect(document.querySelector('[data-ux-datatables-modal]')).not.toBeNull()

        const submitButton = document.querySelector<HTMLButtonElement>(
            '[data-ux-datatables-submit]'
        )

        submitButton?.click()
        await Promise.resolve()

        expect(onSubmit).toHaveBeenCalledWith({ name: 'Desk' })

        adapter.replaceBody(`
            <form id="ux-datatables-edit-form">
                <input name="product[name]" value="Chair" />
            </form>
        `)

        expect(
            document.querySelector('[data-ux-datatables-modal-body]')?.innerHTML
        ).toContain('Chair')

        await adapter.hide()

        expect(adapter.isOpen()).toBe(false)
        expect(document.querySelector('[data-ux-datatables-modal]')).toBeNull()
    })

    it('calls onCancel when the user closes the dialog', async () => {
        const adapter = new DialogModalAdapter()
        const onCancel = vi.fn()

        await adapter.show(
            `
                <dialog data-ux-datatables-modal>
                    <div data-ux-datatables-modal-body>
                        <form id="ux-datatables-edit-form"></form>
                    </div>
                    <button type="button" data-ux-datatables-cancel>Cancel</button>
                    <button type="button" data-ux-datatables-submit>Save</button>
                </dialog>
            `,
            { onSubmit: vi.fn(), onCancel }
        )

        document.querySelector<HTMLButtonElement>('[data-ux-datatables-cancel]')?.click()

        expect(onCancel).toHaveBeenCalledTimes(1)
        expect(adapter.isOpen()).toBe(false)
    })

    it('does not call onCancel when hide is called programmatically', async () => {
        const adapter = new DialogModalAdapter()
        const onCancel = vi.fn()

        await adapter.show(
            `
                <dialog data-ux-datatables-modal>
                    <div data-ux-datatables-modal-body>
                        <form id="ux-datatables-edit-form"></form>
                    </div>
                    <button type="button" data-ux-datatables-submit>Save</button>
                </dialog>
            `,
            { onSubmit: vi.fn(), onCancel }
        )

        await adapter.hide()

        expect(onCancel).not.toHaveBeenCalled()
    })
})
