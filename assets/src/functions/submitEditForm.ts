type SubmitEditFormPayload = {
    entity: string
    id: string
    columns: Record<string, any>[]
    formData: Record<string, any>
    topics?: string[]
}

type SubmitEditFormResponse = {
    success: boolean
    html?: string
}

export async function submitEditForm(
    payload: SubmitEditFormPayload
): Promise<SubmitEditFormResponse> {
    const response = await fetch('/datatables/ajax/edit-form', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            entity: payload.entity,
            id: payload.id,
            columns: payload.columns,
            formData: payload.formData,
            topics: payload.topics ?? [],
        }),
    })

    return response.json()
}
