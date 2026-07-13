export async function toggleBooleanValue({ id, entity, field, newValue, url, method = 'PATCH', }) {
    const numericId = Number(id);
    const body = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        entity,
        field,
        newValue,
    };
    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=toggleBooleanValue.js.map