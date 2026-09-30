import { createMutationHeaders } from './createMutationHeaders.js';
export async function deleteEntity({ dataTable, id, csrfToken, }) {
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id: isNaN(Number(id)) ? id : Number(id) }),
    });
}
//# sourceMappingURL=deleteEntity.js.map