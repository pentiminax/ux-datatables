type FetchEditFormPayload = {
    entity: string
    id: string
    dataTableClass: string | null
}

type FetchEditFormResponse = {
    success: boolean
    html: string
}

export async function fetchEditForm(payload: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    const params = new URLSearchParams({
        entity: payload.entity,
        id: payload.id,
    })

    if (payload.dataTableClass) {
        params.append('dataTableClass', payload.dataTableClass)
    }

    const response = await fetch(`/datatables/ajax/edit-form?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })

    return response.json()
}
