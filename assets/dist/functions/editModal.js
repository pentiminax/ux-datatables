import { Modal } from 'bootstrap';
let modalInstance = null;
export function createEditModal() {
    if (modalInstance) {
        return modalInstance;
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
                    <button type="button" class="btn btn-primary ux-datatables-save-btn">
                        Save
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modalEl);
    const bodyEl = modalEl.querySelector('.modal-body');
    const saveBtn = modalEl.querySelector('.ux-datatables-save-btn');
    if (!Modal) {
        throw new Error('Bootstrap 5 Modal is required for the edit feature.');
    }
    const bsModal = new Modal(modalEl);
    let currentSubmitHandler = null;
    saveBtn.addEventListener('click', async () => {
        if (!currentSubmitHandler) {
            return;
        }
        const form = bodyEl.querySelector('#ux-datatables-edit-form');
        if (!form) {
            return;
        }
        const formData = extractFormData(form);
        saveBtn.disabled = true;
        saveBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        try {
            await currentSubmitHandler(formData);
        }
        finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
    });
    modalInstance = {
        show(html, onSubmit) {
            bodyEl.innerHTML = html;
            currentSubmitHandler = onSubmit;
            bsModal.show();
        },
        hide() {
            bsModal.hide();
            currentSubmitHandler = null;
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