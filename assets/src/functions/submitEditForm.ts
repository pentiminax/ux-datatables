import { createMutationHeaders } from './createMutationHeaders.js'
import { postRowRequest } from './postRowRequest.js'

type SubmitEditFormPayload = {
    dataTable: string
    id: string
    formData: Record<string, any>
    csrfToken?: string
}

type SubmitEditFormResponse = {
    success: boolean
    html?: string
    response: Response
}

export function submitEditForm(payload: SubmitEditFormPayload): Promise<SubmitEditFormResponse> {
    const { csrfToken, ...body } = payload

    return postRowRequest('/datatables/ajax/edit-form', createMutationHeaders(csrfToken), body, {
        success: false,
    })
}
