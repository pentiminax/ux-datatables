import { createMutationHeaders } from './createMutationHeaders.js'
import { postRowRequest } from './postRowRequest.js'
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js'

type SubmitEditFormPayload = {
    dataTable: string
    id: string
    formData: Record<string, any>
    csrfToken?: string
    query?: Record<string, unknown>
    url?: string
}

type SubmitEditFormResponse = {
    success: boolean
    html?: string
    response: Response
}

export function submitEditForm(payload: SubmitEditFormPayload): Promise<SubmitEditFormResponse> {
    const { csrfToken, url = DEFAULT_ROW_ACTION_URLS.editFormSubmit, ...body } = payload

    return postRowRequest(url, createMutationHeaders(csrfToken), body, {
        success: false,
    })
}
