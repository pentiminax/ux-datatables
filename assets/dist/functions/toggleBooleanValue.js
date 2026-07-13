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
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    return await fetch(url, {
        method,
        headers,
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=toggleBooleanValue.js.map