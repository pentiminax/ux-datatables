import { createMutationHeaders } from './createMutationHeaders.js'

export async function deleteEntity({
    dataTable,
    id,
    csrfToken,
    query,
    url,
}: {
    dataTable: string
    id: string
    csrfToken?: string
    query?: Record<string, unknown>
    url: string
}): Promise<Response> {
    return await fetch(url, {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id, query }),
    })
}
