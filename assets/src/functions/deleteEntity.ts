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
    // Keep the id as a string. Coercing through Number() collapses zero-padded keys
    // ("00123" → 123), scientific-notation keys ("1e3" → 1000), and values above
    // Number.MAX_SAFE_INTEGER — the same shapes bulk selection already preserves.
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({ dataTable, id }),
    })
}
