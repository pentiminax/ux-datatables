import type { ModalAdapter, ModalHandlers } from './ModalAdapter.js'
import { createModalRoot, extractFormData } from './modalUtils.js'

export class DialogModalAdapter implements ModalAdapter {
    private dialog: HTMLDialogElement | null = null
    private modalBody: HTMLElement | null = null
    private submitButton: HTMLButtonElement | null = null
    private handlers: ModalHandlers | null = null
    private notifyCancelOnClose = true
    private hideResolver: (() => void) | null = null
    private readonly cancelButtons: HTMLButtonElement[] = []

    private readonly closeListener = (): void => {
        const shouldCancel = this.notifyCancelOnClose
        const onCancel = this.handlers?.onCancel

        this.cleanup()
        this.hideResolver?.()
        this.hideResolver = null

        if (shouldCancel) {
            onCancel?.()
        }
    }

    private readonly submitListener = async (): Promise<void> => {
        if (!this.handlers || !this.submitButton || !this.modalBody) {
            return
        }

        const form = this.modalBody.querySelector<HTMLFormElement>('#ux-datatables-edit-form')

        if (!form) {
            console.error('[ux-datatables] Missing #ux-datatables-edit-form inside the modal body.')

            return
        }

        const originalLabel = this.submitButton.innerHTML
        this.submitButton.disabled = true
        this.submitButton.textContent = 'Saving...'

        try {
            await this.handlers.onSubmit(extractFormData(form))
        } finally {
            if (this.submitButton) {
                this.submitButton.disabled = false
                this.submitButton.innerHTML = originalLabel
            }
        }
    }

    private readonly cancelListener = (): void => {
        this.dialog?.close()
    }

    async show(html: string, handlers: ModalHandlers): Promise<void> {
        this.cleanup()
        this.handlers = handlers
        this.notifyCancelOnClose = true

        const modalRoot = createModalRoot(html)

        if (!modalRoot) {
            return
        }

        if (!(modalRoot instanceof HTMLDialogElement)) {
            console.error('[ux-datatables] DialogModalAdapter requires a <dialog data-ux-datatables-modal> element.')

            return
        }

        const modalBody = modalRoot.querySelector<HTMLElement>('[data-ux-datatables-modal-body]')
        const submitButton = modalRoot.querySelector<HTMLButtonElement>('[data-ux-datatables-submit]')

        if (!modalBody || !submitButton) {
            console.error(
                '[ux-datatables] Edit modal template must include [data-ux-datatables-modal-body] and [data-ux-datatables-submit].'
            )

            return
        }

        document.body.appendChild(modalRoot)

        this.dialog = modalRoot
        this.modalBody = modalBody
        this.submitButton = submitButton

        modalRoot.addEventListener('close', this.closeListener)
        submitButton.addEventListener('click', this.submitListener)

        for (const cancelButton of modalRoot.querySelectorAll<HTMLButtonElement>(
            '[data-ux-datatables-cancel]'
        )) {
            cancelButton.addEventListener('click', this.cancelListener)
            this.cancelButtons.push(cancelButton)
        }

        modalRoot.showModal()
    }

    replaceBody(html: string): void {
        if (!this.modalBody) {
            console.error('[ux-datatables] Cannot replace modal body before the modal is shown.')

            return
        }

        this.modalBody.innerHTML = html
    }

    hide(): Promise<void> {
        if (!this.dialog?.open) {
            this.cleanup()

            return Promise.resolve()
        }

        this.notifyCancelOnClose = false

        return new Promise((resolve) => {
            this.hideResolver = resolve
            this.dialog?.close()
        })
    }

    isOpen(): boolean {
        return this.dialog?.open ?? false
    }

    private cleanup(): void {
        if (this.submitButton) {
            this.submitButton.removeEventListener('click', this.submitListener)
        }

        for (const cancelButton of this.cancelButtons) {
            cancelButton.removeEventListener('click', this.cancelListener)
        }

        this.cancelButtons.length = 0

        if (this.dialog) {
            this.dialog.removeEventListener('close', this.closeListener)

            if (this.dialog.open) {
                this.dialog.close()
            }

            this.dialog.remove()
        }

        this.dialog = null
        this.modalBody = null
        this.submitButton = null
        this.handlers = null
    }
}
