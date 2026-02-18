export async function deleteRow({ id, url }) {
    return await fetch(url, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id }),
    });
}
//# sourceMappingURL=deleteRow.js.map