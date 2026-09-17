import { createMutationHeaders } from './createMutationHeaders.js'

type SubmitEditFormPayload = {
    dataTable: string
    id: string
    formData: Record<string, any>
    csrfToken?: string
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
        headers: createMutationHeaders(payload.csrfToken),
        body: JSON.stringify({
            dataTable: payload.dataTable,
            id: payload.id,
            formData: payload.formData,
        }),
    })

    return response.json()
}
