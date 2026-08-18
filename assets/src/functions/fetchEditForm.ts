type FetchEditFormPayload = {
    dataTable: string
    id: string
}

type FetchEditFormResponse = {
    success: boolean
    html: string
}

export async function fetchEditForm(payload: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    const response = await fetch('/datatables/ajax/edit-form/view', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ dataTable: payload.dataTable, id: payload.id }),
    })

    return response.json()
}
