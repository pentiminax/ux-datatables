type FetchDetailRowPayload = {
    entity: string
    id: string
    dataTableClass: string | null
}

type FetchDetailRowResponse = {
    success: boolean
    html: string
}

export async function fetchDetailRow(
    payload: FetchDetailRowPayload
): Promise<FetchDetailRowResponse> {
    const params = new URLSearchParams({
        entity: payload.entity,
        id: payload.id,
    })

    if (payload.dataTableClass) {
        params.append('dataTableClass', payload.dataTableClass)
    }

    const response = await fetch(`/datatables/ajax/detail?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })

    return response.json()
}
