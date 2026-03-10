type ToggleBooleanPayload = {
  id: string
  entity: string
  field: string
  newValue: boolean
  url: string
  method?: string
  topics?: string[]
}

export async function toggleBooleanValue({
  id,
  entity,
  field,
  newValue,
  url,
  method = 'PATCH',
  topics,
}: ToggleBooleanPayload): Promise<Response> {
  const body: Record<string, unknown> = { id: parseInt(id), entity, field, newValue }

  if (Array.isArray(topics) && topics.length > 0) {
    body.topics = topics
  }

  return await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(body),
  })
}
