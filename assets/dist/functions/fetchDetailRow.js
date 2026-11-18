import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
export function fetchDetailRow({ url, ...payload }) {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchDetailRow.js.map