import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js';
export function fetchDetailRow({ url = DEFAULT_ROW_ACTION_URLS.detail, ...payload }) {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchDetailRow.js.map