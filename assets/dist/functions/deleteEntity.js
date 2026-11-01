import { createMutationHeaders } from './createMutationHeaders.js';
export async function deleteEntity({ dataTable, id, csrfToken, query, }) {
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id, query }),
    });
}
//# sourceMappingURL=deleteEntity.js.map