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
