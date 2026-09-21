import type { ModalAdapter } from '../modal/ModalAdapter.js'
import { resolveModalAdapter } from '../modal/resolveModalAdapter.js'
import type { StyleFramework } from '../types/styleFramework.js'

const BOOTSTRAP_FRAMEWORKS: StyleFramework[] = ['bs', 'bs4', 'bs5']

export interface ConfirmRequest {
    message: string
    confirmLabel: string
    cancelLabel: string
    framework: StyleFramework
    adapterKey?: string | null
}

/**
 * Ask for a confirmation through the modal adapter the table already uses.
 *
 * ponytail: the adapters were written for the edit modal, so the body must carry an
 * `#ux-datatables-edit-form` for their submit button to fire. A dedicated confirm contract on
 * ModalAdapter would drop this, but it would break every custom adapter.
 */
export async function confirmBulkAction(request: ConfirmRequest): Promise<boolean> {
    const modal = await resolveModalAdapter(request.adapterKey ?? null, request.framework)

    if (!modal) {
        return confirm(request.message)
    }

    return await new Promise<boolean>((resolve) => {
        let answered = false

        const answer = async (value: boolean, adapter: ModalAdapter): Promise<void> => {
            if (answered) {
                return
            }

            answered = true

            if (value) {
                await adapter.hide()
            }

            resolve(value)
        }

        void modal.show(buildHtml(request), {
            onSubmit: async () => {
                await answer(true, modal)
            },
            onCancel: () => {
                void answer(false, modal)
            },
        })
    })
}

function buildHtml(request: ConfirmRequest): string {
    const message = escapeHtml(request.message)
    const confirmLabel = escapeHtml(request.confirmLabel)
    const cancelLabel = escapeHtml(request.cancelLabel)
    const body = `<form id="ux-datatables-edit-form"><p class="dt-bulk-confirm__message">${message}</p></form>`

    if (BOOTSTRAP_FRAMEWORKS.includes(request.framework)) {
        return `<div class="modal fade" tabindex="-1" aria-hidden="true" data-ux-datatables-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body" data-ux-datatables-modal-body>${body}</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-ux-datatables-cancel>${cancelLabel}</button>
                <button type="button" class="btn btn-primary" data-ux-datatables-submit>${confirmLabel}</button>
            </div>
        </div>
    </div>
</div>`
    }

    return `<dialog class="dt-modal" data-ux-datatables-modal>
    <div class="dt-modal-body" data-ux-datatables-modal-body>${body}</div>
    <div class="dt-modal-footer">
        <button type="button" class="dt-button" data-ux-datatables-cancel>${cancelLabel}</button>
        <button type="button" class="dt-button dt-button-primary" data-ux-datatables-submit>${confirmLabel}</button>
    </div>
</dialog>`
}

function escapeHtml(value: string): string {
    const element = document.createElement('span')
    element.textContent = value

    return element.innerHTML
}
