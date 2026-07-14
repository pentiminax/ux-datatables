import { createMutationHeaders } from './createMutationHeaders.js';
export async function toggleBooleanValue({ id, entity, field, newValue, url, method = 'PATCH', topics, csrfToken, }) {
    const numericId = Number(id);
    const body = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        entity,
        field,
        newValue,
    };
    if (Array.isArray(topics) && topics.length > 0) {
        body.topics = topics;
    }
    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=toggleBooleanValue.js.map