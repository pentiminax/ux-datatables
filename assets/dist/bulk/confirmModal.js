import { resolveModalAdapter } from '../modal/resolveModalAdapter.js';
const BOOTSTRAP_FRAMEWORKS = ['bs', 'bs4', 'bs5'];
export async function confirmBulkAction(request) {
    const modal = await resolveModalAdapter(request.adapterKey ?? null, request.framework);
    if (!modal) {
        return confirm(request.message);
    }
    return await new Promise((resolve) => {
        let answered = false;
        const answer = async (value, adapter) => {
            if (answered) {
                return;
            }
            answered = true;
            if (value) {
                await adapter.hide();
            }
            resolve(value);
        };
        void modal.show(buildHtml(request), {
            onSubmit: async () => {
                await answer(true, modal);
            },
            onCancel: () => {
                void answer(false, modal);
            },
        });
    });
}
function buildHtml(request) {
    const message = escapeHtml(request.message);
    const confirmLabel = escapeHtml(request.confirmLabel);
    const cancelLabel = escapeHtml(request.cancelLabel);
    const body = `<form id="ux-datatables-edit-form"><p class="dt-bulk-confirm__message">${message}</p></form>`;
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
</div>`;
    }
    return `<dialog class="dt-modal" data-ux-datatables-modal>
    <div class="dt-modal-body" data-ux-datatables-modal-body>${body}</div>
    <div class="dt-modal-footer">
        <button type="button" class="dt-button" data-ux-datatables-cancel>${cancelLabel}</button>
        <button type="button" class="dt-button dt-button-primary" data-ux-datatables-submit>${confirmLabel}</button>
    </div>
</dialog>`;
}
function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}
//# sourceMappingURL=confirmModal.js.map