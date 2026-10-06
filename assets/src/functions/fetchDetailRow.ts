type FetchDetailRowPayload = {
    dataTable: string
    id: string
}

type FetchDetailRowResponse = {
    success: boolean
    html: string
    response: Response
}

export async function fetchDetailRow(
    payload: FetchDetailRowPayload
): Promise<FetchDetailRowResponse> {
    const response = await fetch('/datatables/ajax/detail', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ dataTable: payload.dataTable, id: payload.id }),
    })

    const body = await response.json().catch(() => ({ success: false, html: '' }))

    return { ...body, response }
}
