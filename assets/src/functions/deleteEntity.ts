export async function deleteEntity({
    entity,
    id,
    topics,
    csrfToken,
}: {
    entity: string
    id: string
    topics?: string[]
    csrfToken?: string
}): Promise<Response> {
    const body: Record<string, unknown> = { entity, id: isNaN(Number(id)) ? id : Number(id) }

    if (topics && topics.length > 0) {
        body.topics = topics
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
