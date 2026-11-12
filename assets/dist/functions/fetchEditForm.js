import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js';
export function fetchEditForm({ url = DEFAULT_ROW_ACTION_URLS.editForm, ...payload }) {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchEditForm.js.map