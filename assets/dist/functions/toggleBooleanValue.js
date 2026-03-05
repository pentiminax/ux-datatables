export async function toggleBooleanValue({ id, entity, field, newValue, url, method = 'PATCH', topic, }) {
    const body = { id: parseInt(id), entity, field, newValue };
    if (topic !== undefined) {
        body.topic = topic;
    }
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