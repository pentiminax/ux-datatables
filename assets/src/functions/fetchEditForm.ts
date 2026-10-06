import { JSON_HEADERS, postRowRequest } from './postRowRequest.js'

type FetchEditFormPayload = {
    dataTable: string
    id: string
}

type FetchEditFormResponse = {
    success: boolean
    html: string
    response: Response
}

export function fetchEditForm(payload: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    return postRowRequest('/datatables/ajax/edit-form/view', JSON_HEADERS, payload, {
        success: false,
        html: '',
    })
}
