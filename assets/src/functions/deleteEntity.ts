import { createMutationHeaders } from './createMutationHeaders.js'
import { DEFAULT_ROW_ACTION_URLS } from './rowActionUrl.js'

export async function deleteEntity({
    dataTable,
    id,
    csrfToken,
    query,
    url = DEFAULT_ROW_ACTION_URLS.delete,
}: {
    dataTable: string
    id: string
    csrfToken?: string
    query?: Record<string, unknown>
    url?: string
}): Promise<Response> {
    return await fetch(url, {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id, query }),
    })
}
