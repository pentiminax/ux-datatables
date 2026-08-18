type FetchEditFormPayload = {
    dataTable: string
    id: string
}

type FetchEditFormResponse = {
    success: boolean
    html: string
}

export async function fetchEditForm(payload: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    const params = new URLSearchParams({
        dataTable: payload.dataTable,
        id: payload.id,
    })

    const response = await fetch(`/datatables/ajax/edit-form?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })

    return response.json()
}
