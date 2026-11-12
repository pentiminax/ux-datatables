import { JSON_HEADERS, postRowRequest } from './postRowRequest.js'
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js'

type FetchEditFormPayload = {
    dataTable: string
    id: string
    query?: Record<string, unknown>
    url?: string
}

type FetchEditFormResponse = {
    success: boolean
    html: string
    response: Response
}

export function fetchEditForm({
    url = DEFAULT_ROW_ACTION_URLS.editForm,
    ...payload
}: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    })
}
