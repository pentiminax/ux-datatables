export async function toggleBooleanValue({ id, entity, field, newValue, url, method = 'PATCH', }) {
    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id: parseInt(id), entity, field, newValue }),
    });
}
//# sourceMappingURL=toggleBooleanValue.js.map