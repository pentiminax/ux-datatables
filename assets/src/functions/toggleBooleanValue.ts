import { createMutationHeaders } from './createMutationHeaders.js'

type ToggleBooleanPayload = {
    id: string
    entity: string
    field: string
    newValue: boolean
    url: string
    method?: string
    dataTableClass?: string | null
    csrfToken?: string
}

export async function toggleBooleanValue({
    id,
    entity,
    field,
    newValue,
    url,
    method = 'PATCH',
    dataTableClass,
    csrfToken,
}: ToggleBooleanPayload): Promise<Response> {
    const numericId = Number(id)
    const body: Record<string, unknown> = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        entity,
        field,
        newValue,
    }

    if (dataTableClass) {
        body.dataTableClass = dataTableClass
    }

    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    })
}
