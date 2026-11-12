import { createMutationHeaders } from './createMutationHeaders.js';
import { postRowRequest } from './postRowRequest.js';
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js';
export function submitEditForm(payload) {
    const { csrfToken, url = DEFAULT_ROW_ACTION_URLS.editFormSubmit, ...body } = payload;
    return postRowRequest(url, createMutationHeaders(csrfToken), body, {
        success: false,
    });
}
//# sourceMappingURL=submitEditForm.js.map