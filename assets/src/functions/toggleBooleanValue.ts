import { createMutationHeaders } from './createMutationHeaders.js'

type ToggleBooleanPayload = {
    id: string
    entity: string
    field: string
    newValue: boolean
    url: string
    method?: string
    topics?: string[]
    csrfToken?: string
}

export async function toggleBooleanValue({
    id,
    entity,
    field,
    newValue,
    url,
    method = 'PATCH',
    topics,
    csrfToken,
}: ToggleBooleanPayload): Promise<Response> {
    const numericId = Number(id)
    const body: Record<string, unknown> = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        entity,
        field,
        newValue,
    }

    if (Array.isArray(topics) && topics.length > 0) {
        body.topics = topics
    }

    return await fetch(url, {
        method,
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    })
}
