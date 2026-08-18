type FetchDetailRowPayload = {
    dataTable: string
    id: string
}

type FetchDetailRowResponse = {
    success: boolean
    html: string
}

export async function fetchDetailRow(
    payload: FetchDetailRowPayload
): Promise<FetchDetailRowResponse> {
    const params = new URLSearchParams({
        dataTable: payload.dataTable,
        id: payload.id,
    })

    const response = await fetch(`/datatables/ajax/detail?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })

    return response.json()
}
