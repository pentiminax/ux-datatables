import { createMutationHeaders } from './createMutationHeaders.js';
import { postRowRequest } from './postRowRequest.js';
export function submitEditForm(payload) {
    const { csrfToken, ...body } = payload;
    return postRowRequest('/datatables/ajax/edit-form', createMutationHeaders(csrfToken), body, {
        success: false,
    });
}
//# sourceMappingURL=submitEditForm.js.map