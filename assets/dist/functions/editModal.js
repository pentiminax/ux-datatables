let bootstrapModal = null;
let bootstrapDetected = null;
async function getBootstrapModal() {
    if (bootstrapDetected !== null) {
        return bootstrapModal;
    }
    try {
        const bootstrap = await import('bootstrap');
        bootstrapModal = bootstrap.Modal;
        bootstrapDetected = true;
    }
    catch {
        bootstrapModal = null;
        bootstrapDetected = false;
    }
    return bootstrapModal;
}
let modalInstance = null;
export async function createEditModal() {
    if (modalInstance) {
        return modalInstance;
    }
    const ModalClass = await getBootstrapModal();
    if (!ModalClass) {
        console.error('[ux-datatables] Bootstrap 5 is required for the edit modal feature.');
        return null;
    }
    const modalEl = document.createElement('div');
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary ux-datatables-save-btn">Save</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modalEl);
    const bodyEl = modalEl.querySelector('.modal-body');
    const saveBtn = modalEl.querySelector('.ux-datatables-save-btn');
    const bsModal = new ModalClass(modalEl);
    let currentHandler = null;
    saveBtn.addEventListener('click', async () => {
        if (!currentHandler)
            return;
        const form = bodyEl.querySelector('#ux-datatables-edit-form');
        if (!form)
            return;
        const formData = extractFormData(form);
        saveBtn.disabled = true;
        saveBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        try {
            await currentHandler(formData);
        }
        finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
    });
    modalInstance = {
        show(html, onSubmit) {
            bodyEl.innerHTML = html;
            currentHandler = onSubmit;
            bsModal.show();
        },
        hide() {
            bsModal.hide();
            currentHandler = null;
        },
        showErrors(html) {
            bodyEl.innerHTML = html;
        },
    };
    return modalInstance;
}
function extractFormData(form) {
    const data = {};
    const formData = new FormData(form);
    const firstInput = form.querySelector('input:not([type=hidden]), select, textarea');
    const nameMatch = firstInput?.name?.match(/^([^[]+)\[/);
    const prefix = nameMatch ? nameMatch[1] : null;
    formData.forEach((value, key) => {
        if (prefix) {
            const fieldMatch = key.match(new RegExp(`^${prefix}\\[([^\\]]+)\\]$`));
            if (fieldMatch) {
                data[fieldMatch[1]] = value;
                return;
            }
        }
        data[key] = value;
    });
    return data;
}
//# sourceMappingURL=editModal.js.map