import { afterEach, describe, expect, it, vi } from 'vitest'
import { BootstrapModalAdapter } from '../BootstrapModalAdapter.js'

vi.mock('bootstrap', () => ({
    Modal: class {
        constructor(private readonly element: HTMLElement) {}

        show(): void {}

        hide(): void {
            this.element.dispatchEvent(new Event('hidden.bs.modal'))
        }

        dispose(): void {}
    },
}))

describe('BootstrapModalAdapter', () => {
    afterEach(() => {
        document.body.innerHTML = ''
        vi.restoreAllMocks()
    })

    it('renders the modal, submits the form, replaces the body, and cleans up on hide', async () => {
        const adapter = new BootstrapModalAdapter()
        const onSubmit = vi.fn().mockResolvedValue(undefined)

        await adapter.show(
            `
                <div data-ux-datatables-modal>
                    <div data-ux-datatables-modal-body>
                        <form id="ux-datatables-edit-form">
                            <input name="product[name]" value="Desk" />
                        </form>
                    </div>
                    <button type="button" data-ux-datatables-submit>Save</button>
                </div>
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
})
