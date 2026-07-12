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

    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }

    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken
    }

    return await fetch(url, {
        method,
        headers,
        body: JSON.stringify(body),
    })
}
