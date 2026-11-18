import { JSON_HEADERS, postRowRequest } from './postRowRequest.js'

type FetchDetailRowPayload = {
    dataTable: string
    id: string
    query?: Record<string, unknown>
    url: string
}

type FetchDetailRowResponse = {
    success: boolean
    html: string
    response: Response
}

export function fetchDetailRow({
    url,
    ...payload
}: FetchDetailRowPayload): Promise<FetchDetailRowResponse> {
    return postRowRequest(url, JSON_HEADERS, payload, {
        success: false,
        html: '',
    })
}
