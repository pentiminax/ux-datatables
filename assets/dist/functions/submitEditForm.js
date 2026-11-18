import { createMutationHeaders } from './createMutationHeaders.js';
import { postRowRequest } from './postRowRequest.js';
export function submitEditForm(payload) {
    const { csrfToken, url, ...body } = payload;
    return postRowRequest(url, createMutationHeaders(csrfToken), body, {
        success: false,
    });
}
//# sourceMappingURL=submitEditForm.js.map