import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
export function fetchEditForm({ url, ...payload }) {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchEditForm.js.map