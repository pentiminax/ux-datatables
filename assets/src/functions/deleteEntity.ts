export async function deleteEntity({
  entity,
  id,
  topics,
}: {
  entity: string
  id: string
  topics?: string[]
}): Promise<Response> {
  const body: Record<string, unknown> = { entity, id: isNaN(Number(id)) ? id : Number(id) }

  if (topics && topics.length > 0) {
    body.topics = topics
  }

  return await fetch('/datatables/ajax/delete', {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(body),
  })
}
