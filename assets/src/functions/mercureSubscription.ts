export interface MercureConfig {
  hubUrl: string
  topics: string[]
  withCredentials?: boolean
  debounceMs?: number
}

export function createMercureSubscription(
  config: MercureConfig,
  onMessage: (event: MessageEvent) => void
): EventSource {
  const url = new URL(config.hubUrl, window.location.href)

  for (const topic of config.topics) {
    url.searchParams.append('topic', topic)
  }

  const eventSource = new EventSource(url.toString(), {
    withCredentials: config.withCredentials ?? false,
  })

  const debounceMs = config.debounceMs ?? 500
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  eventSource.onmessage = (event: MessageEvent) => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer)
    }
    debounceTimer = setTimeout(() => {
      onMessage(event)
      debounceTimer = null
    }, debounceMs)
  }

  return eventSource
}
