export async function deleteEntity({
    entity,
    id,
    dataTableClass,
    csrfToken,
}: {
    entity: string
    id: string
    dataTableClass?: string | null
    csrfToken?: string
}): Promise<Response> {
    const body: Record<string, unknown> = { entity, id: isNaN(Number(id)) ? id : Number(id) }

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

    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers,
        body: JSON.stringify(body),
    })
}
