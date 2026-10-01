import { createMutationHeaders } from './createMutationHeaders.js';
export async function toggleBooleanValue({ id, field, newValue, url, method = 'PATCH', dataTable, csrfToken, }) {
    const body = {
        id,
        field,
        newValue,
        dataTable,
    };
    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=toggleBooleanValue.js.map