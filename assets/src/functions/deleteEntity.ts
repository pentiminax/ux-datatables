import { createMutationHeaders } from './createMutationHeaders.js'

export async function deleteEntity({
    dataTable,
    id,
    csrfToken,
}: {
    dataTable: string
    id: string
    csrfToken?: string
}): Promise<Response> {
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id: isNaN(Number(id)) ? id : Number(id) }),
    })
}
