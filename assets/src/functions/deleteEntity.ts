import { createMutationHeaders } from './createMutationHeaders.js'

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

    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify(body),
    })
}
