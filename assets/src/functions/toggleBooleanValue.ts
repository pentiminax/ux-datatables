import { createMutationHeaders } from './createMutationHeaders.js'

type ToggleBooleanPayload = {
    id: string
    rowId?: string
    field: string
    newValue: boolean
    url: string
    method?: string
    dataTable: string
    csrfToken?: string
    query?: Record<string, unknown>
}

export async function toggleBooleanValue({
    id,
    rowId,
    field,
    newValue,
    url,
    method = 'PATCH',
    dataTable,
    csrfToken,
    query,
}: ToggleBooleanPayload): Promise<Response> {
    const body: Record<string, unknown> = {
        id,
        rowId,
        field,
        newValue,
        dataTable,
        query,
    }

    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    })
}
