import type { ModalAdapter, ModalHandlers } from './ModalAdapter.js'
import { createModalRoot, extractFormData } from './modalUtils.js'

type BootstrapModalInstance = {
    dispose?: () => void
    hide: () => void
    show: () => void
}

type BootstrapModalConstructor = new (element: HTMLElement) => BootstrapModalInstance

async function loadBootstrapModal(): Promise<BootstrapModalConstructor | null> {
    try {
        const bootstrap = await import('bootstrap')

        return bootstrap.Modal ?? null
    } catch {
        console.error('[ux-datatables] Bootstrap is required for the BootstrapModalAdapter.')

        return null
    }
}

export class BootstrapModalAdapter implements ModalAdapter {
    private modalRoot: HTMLElement | null = null
    private modalBody: HTMLElement | null = null
    private submitButton: HTMLButtonElement | null = null
    private modalInstance: BootstrapModalInstance | null = null
    private handlers: ModalHandlers | null = null
    private open = false
    private notifyCancelOnHide = true
    private hideResolver: (() => void) | null = null

    private readonly hiddenListener = (): void => {
        const shouldCancel = this.notifyCancelOnHide
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
        this.submitButton.innerHTML =
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'

        try {
            await this.handlers.onSubmit(extractFormData(form))
        } finally {
            if (this.submitButton) {
                this.submitButton.disabled = false
                this.submitButton.innerHTML = originalLabel
            }
        }
    }

    async show(html: string, handlers: ModalHandlers): Promise<void> {
        this.cleanup()
        this.handlers = handlers
        this.notifyCancelOnHide = true

        const modalRoot = createModalRoot(html)

        if (!modalRoot) {
            return
        }

        const modalBody = modalRoot.querySelector<HTMLElement>('[data-ux-datatables-modal-body]')
        const submitButton = modalRoot.querySelector<HTMLButtonElement>(
            '[data-ux-datatables-submit]'
        )

        if (!modalBody || !submitButton) {
            console.error(
                '[ux-datatables] Edit modal template must include [data-ux-datatables-modal-body] and [data-ux-datatables-submit].'
            )

            return
        }

        const ModalClass = await loadBootstrapModal()

        if (!ModalClass) {
            return
        }

        document.body.appendChild(modalRoot)

        this.modalRoot = modalRoot
        this.modalBody = modalBody
        this.submitButton = submitButton
        this.modalInstance = new ModalClass(modalRoot)

        this.modalRoot.addEventListener('hidden.bs.modal', this.hiddenListener)
        this.submitButton.addEventListener('click', this.submitListener)

        this.modalInstance.show()
        this.open = true
    }

    replaceBody(html: string): void {
        if (!this.modalBody) {
            console.error('[ux-datatables] Cannot replace modal body before the modal is shown.')

            return
        }

        this.modalBody.innerHTML = html
    }

    hide(): Promise<void> {
        if (!this.modalInstance || !this.open) {
            this.cleanup()

            return Promise.resolve()
        }

        this.notifyCancelOnHide = false

        return new Promise((resolve) => {
            this.hideResolver = resolve
            this.modalInstance?.hide()
        })
    }

    isOpen(): boolean {
        return this.open
    }

    private cleanup(): void {
        this.open = false

        if (this.submitButton) {
            this.submitButton.removeEventListener('click', this.submitListener)
        }

        if (this.modalRoot) {
            this.modalRoot.removeEventListener('hidden.bs.modal', this.hiddenListener)
        }

        this.modalInstance?.dispose?.()
        this.modalRoot?.remove()

        this.modalInstance = null
        this.modalRoot = null
        this.modalBody = null
        this.submitButton = null
        this.handlers = null
    }
}
