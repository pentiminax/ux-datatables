type FetchEditFormPayload = {
    entity: string
    id: string
    columns: Record<string, unknown>[]
}

type FetchEditFormResponse = {
    success: boolean
    html: string
}

export async function fetchEditForm(payload: FetchEditFormPayload): Promise<FetchEditFormResponse> {
    const params = new URLSearchParams({
        entity: payload.entity,
        id: payload.id,
    })

    appendSearchParams(params, 'columns', payload.columns)

    const response = await fetch(`/datatables/ajax/edit-form?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })

    return response.json()
}

function appendSearchParams(params: URLSearchParams, key: string, value: unknown): void {
    if (Array.isArray(value)) {
        value.forEach((item, index) => appendSearchParams(params, `${key}[${index}]`, item))

        return
    }

    if (value !== null && typeof value === 'object') {
        Object.entries(value).forEach(([nestedKey, nestedValue]) => {
            appendSearchParams(params, `${key}[${nestedKey}]`, nestedValue)
        })

        return
    }

    if (value === null || value === undefined) {
        return
    }

    params.append(key, String(value))
}
