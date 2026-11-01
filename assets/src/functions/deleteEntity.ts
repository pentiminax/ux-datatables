import { createMutationHeaders } from './createMutationHeaders.js'

export async function deleteEntity({
    dataTable,
    id,
    csrfToken,
    query,
}: {
    dataTable: string
    id: string
    csrfToken?: string
    query?: Record<string, unknown>
}): Promise<Response> {
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id, query }),
    })
}
