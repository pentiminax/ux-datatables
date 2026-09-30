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
    // Keep the id as a string. Coercing through Number() collapses zero-padded keys,
    // scientific-notation keys, and values above Number.MAX_SAFE_INTEGER — the same
    // shapes bulk selection and the edit-form path already preserve.
    const body: Record<string, unknown> = {
        id,
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
