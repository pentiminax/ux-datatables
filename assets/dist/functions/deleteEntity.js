import { createMutationHeaders } from './createMutationHeaders.js';
export async function deleteEntity({ entity, id, topics, csrfToken, }) {
    const body = { entity, id: isNaN(Number(id)) ? id : Number(id) };
    if (topics && topics.length > 0) {
        body.topics = topics;
    }
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=deleteEntity.js.map