export async function fetchDetailRow(payload) {
    const params = new URLSearchParams({
        dataTable: payload.dataTable,
        id: payload.id,
    });
    const response = await fetch(`/datatables/ajax/detail?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    return response.json();
}
//# sourceMappingURL=fetchDetailRow.js.map