export interface MercureConfig {
  hubUrl: string
  topic: string
  withCredentials?: boolean
  debounceMs?: number
}

export function createMercureSubscription(
  config: MercureConfig,
  onMessage: (event: MessageEvent) => void,
): EventSource {
  const url = new URL(config.hubUrl, window.location.href)
  url.searchParams.append('topic', config.topic)

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
