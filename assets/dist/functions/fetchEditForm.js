import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
export function fetchEditForm(payload) {
    return postRowRequest('/datatables/ajax/edit-form/view', JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchEditForm.js.map