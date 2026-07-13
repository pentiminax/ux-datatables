type ToggleBooleanPayload = {
    id: string
    entity: string
    field: string
    newValue: boolean
    url: string
    method?: string
}

export async function toggleBooleanValue({
    id,
    entity,
    field,
    newValue,
    url,
    method = 'PATCH',
}: ToggleBooleanPayload): Promise<Response> {
    const numericId = Number(id)
    const body: Record<string, unknown> = {
        id: id.trim() !== '' && Number.isFinite(numericId) ? numericId : id,
        entity,
        field,
        newValue,
    }

    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    })
}
