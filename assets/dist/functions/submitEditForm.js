import { createMutationHeaders } from './createMutationHeaders.js';
export async function submitEditForm(payload) {
    const response = await fetch('/datatables/ajax/edit-form', {
        method: 'POST',
        headers: createMutationHeaders(payload.csrfToken),
        body: JSON.stringify({
            dataTable: payload.dataTable,
            id: payload.id,
            formData: payload.formData,
        }),
    });
    return response.json();
}
//# sourceMappingURL=submitEditForm.js.map