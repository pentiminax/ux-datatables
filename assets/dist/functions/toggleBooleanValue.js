export async function toggleBooleanValue({id, entity, field, value, url, method = 'PATCH'}) {
    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({id, entity, field, value}),
    });
}
