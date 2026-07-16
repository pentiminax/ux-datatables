import { createMutationHeaders } from './createMutationHeaders.js';
export async function deleteEntity({ entity, id, dataTableClass, csrfToken, }) {
    const body = { entity, id: isNaN(Number(id)) ? id : Number(id) };
    if (dataTableClass) {
        body.dataTableClass = dataTableClass;
    }
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=deleteEntity.js.map