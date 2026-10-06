import { JSON_HEADERS, postRowRequest } from './postRowRequest.js';
export function fetchDetailRow(payload) {
    return postRowRequest('/datatables/ajax/detail', JSON_HEADERS, payload, {
        success: false,
        html: '',
    });
}
//# sourceMappingURL=fetchDetailRow.js.map