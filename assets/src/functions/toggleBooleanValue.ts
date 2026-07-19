import { createMutationHeaders } from './createMutationHeaders.js'

type ToggleBooleanPayload = {
    id: string
    field: string
    newValue: boolean
    url: string
    method?: string
    dataTable: string
    csrfToken?: string
}

export async function toggleBooleanValue({
    id,
    field,
    newValue,
    url,
    method = 'PATCH',
    dataTable,
    csrfToken,
}: ToggleBooleanPayload): Promise<Response> {
    const numericId = Number(id)
    const body: Record<string, unknown> = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        field,
        newValue,
        dataTable,
    }

    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    })
}
