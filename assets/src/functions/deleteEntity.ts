export async function deleteEntity({
    entity,
    id,
}: {
    entity: string
    id: string
}): Promise<Response> {
    const body: Record<string, unknown> = { entity, id: isNaN(Number(id)) ? id : Number(id) }

    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    })
}
