import { JSON_HEADERS, postRowRequest } from './postRowRequest.js'

type FetchEditFormPayload = {
    dataTable: string
    id: string
    query?: Record<string, unknown>
    url: string
}

type FetchEditFormResponse = {
    success: boolean
    html: string
    response: Response
}

export function fetchEditForm({
    url,
    ...payload
}: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    })
}
