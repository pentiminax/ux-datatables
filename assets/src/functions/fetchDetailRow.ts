import { JSON_HEADERS, postRowRequest } from './postRowRequest.js'

type FetchDetailRowPayload = {
    dataTable: string
    id: string
}

type FetchDetailRowResponse = {
    success: boolean
    html: string
    response: Response
}

export function fetchDetailRow(payload: FetchDetailRowPayload): Promise<FetchDetailRowResponse> {
    return postRowRequest('/datatables/ajax/detail', JSON_HEADERS, payload, {
        success: false,
        html: '',
    })
}
