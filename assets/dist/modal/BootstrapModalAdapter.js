async function loadBootstrapModal() {
    try {
        const bootstrap = await import('bootstrap');
        return bootstrap.Modal ?? null;
    }
    catch {
        console.error('[ux-datatables] Bootstrap is required for the BootstrapModalAdapter.');
        return null;
    }
}
export class BootstrapModalAdapter {
    constructor() {
        this.modalRoot = null;
        this.modalBody = null;
        this.submitButton = null;
        this.modalInstance = null;
        this.handlers = null;
        this.open = false;
        this.notifyCancelOnHide = true;
        this.hideResolver = null;
        this.hiddenListener = () => {
            const shouldCancel = this.notifyCancelOnHide;
            const onCancel = this.handlers?.onCancel;
            this.cleanup();
            this.hideResolver?.();
            this.hideResolver = null;
            if (shouldCancel) {
                onCancel?.();
            }
        };
        this.submitListener = async () => {
            if (!this.handlers || !this.submitButton || !this.modalBody) {
                return;
            }
            const form = this.modalBody.querySelector('#ux-datatables-edit-form');
            if (!form) {
                console.error('[ux-datatables] Missing #ux-datatables-edit-form inside the modal body.');
                return;
            }
            const originalLabel = this.submitButton.innerHTML;
            this.submitButton.disabled = true;
            this.submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            try {
                await this.handlers.onSubmit(extractFormData(form));
            }
            finally {
                if (this.submitButton) {
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = originalLabel;
                }
            }
        };
    }
    async show(html, handlers) {
        this.cleanup();
        this.handlers = handlers;
        this.notifyCancelOnHide = true;
        const modalRoot = createModalRoot(html);
        if (!modalRoot) {
            return;
        }
        const modalBody = modalRoot.querySelector('[data-ux-datatables-modal-body]');
        const submitButton = modalRoot.querySelector('[data-ux-datatables-submit]');
        if (!modalBody || !submitButton) {
            console.error('[ux-datatables] Edit modal template must include [data-ux-datatables-modal-body] and [data-ux-datatables-submit].');
            return;
        }
        const ModalClass = await loadBootstrapModal();
        if (!ModalClass) {
            return;
        }
        document.body.appendChild(modalRoot);
        this.modalRoot = modalRoot;
        this.modalBody = modalBody;
        this.submitButton = submitButton;
        this.modalInstance = new ModalClass(modalRoot);
        this.modalRoot.addEventListener('hidden.bs.modal', this.hiddenListener);
        this.submitButton.addEventListener('click', this.submitListener);
        this.modalInstance.show();
        this.open = true;
    }
    replaceBody(html) {
        if (!this.modalBody) {
            console.error('[ux-datatables] Cannot replace modal body before the modal is shown.');
            return;
        }
        this.modalBody.innerHTML = html;
    }
    hide() {
        if (!this.modalInstance || !this.open) {
            this.cleanup();
            return Promise.resolve();
        }
        this.notifyCancelOnHide = false;
        return new Promise((resolve) => {
            this.hideResolver = resolve;
            this.modalInstance?.hide();
        });
    }
    isOpen() {
        return this.open;
    }
    cleanup() {
        this.open = false;
        if (this.submitButton) {
            this.submitButton.removeEventListener('click', this.submitListener);
        }
        if (this.modalRoot) {
            this.modalRoot.removeEventListener('hidden.bs.modal', this.hiddenListener);
        }
        this.modalInstance?.dispose?.();
        this.modalRoot?.remove();
        this.modalInstance = null;
        this.modalRoot = null;
        this.modalBody = null;
        this.submitButton = null;
        this.handlers = null;
    }
}
function createModalRoot(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const modalRoot = template.content.querySelector('[data-ux-datatables-modal]');
    if (!modalRoot) {
        console.error('[ux-datatables] Edit modal template must include [data-ux-datatables-modal].');
        return null;
    }
    return modalRoot;
}
function extractFormData(form) {
    const data = {};
    const formData = new FormData(form);
    const firstInput = form.querySelector('input:not([type=hidden]), select, textarea');
    const nameMatch = firstInput?.name?.match(/^([^[]+)\[/);
    const prefix = nameMatch ? nameMatch[1] : null;
    formData.forEach((value, key) => {
        let normalizedKey = key;
        if (prefix) {
            const fieldMatch = key.match(new RegExp(`^${prefix}\\[([^\\]]+)\\]$`));
            if (fieldMatch) {
                normalizedKey = fieldMatch[1];
            }
        }
        const currentValue = data[normalizedKey];
        if (undefined === currentValue) {
            data[normalizedKey] = value;
            return;
        }
        if (Array.isArray(currentValue)) {
            currentValue.push(value);
            return;
        }
        data[normalizedKey] = [currentValue, value];
    });
    return data;
}
//# sourceMappingURL=BootstrapModalAdapter.js.map