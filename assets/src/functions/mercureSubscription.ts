export interface MercureConfig {
    hubUrl: string
    topics: string[]
    protocolVersion?: string
    withCredentials?: boolean
    debounceMs?: number
}

/** The Mercure protocol version whose subscriptions use URI Template selectors. */
const LEGACY_PROTOCOL_VERSION = '0.x'

/**
 * Topic matcher query parameters. `topic` is the 0.x spelling, dropped in 1.0 in favour of
 * `match` (exact) and `match_urlpattern` (WHATWG URL Pattern).
 */
const LEGACY_TOPIC_PARAMETER = 'topic'
const EXACT_MATCH_PARAMETER = 'match'
const URL_PATTERN_MATCH_PARAMETER = 'match_urlpattern'

/** A `{placeholder}` with a simple name, the only URI Template shape a URL Pattern can express. */
const NAMED_PLACEHOLDER = /\{([^{}]*)\}/g
const PLACEHOLDER_NAME = /^[A-Za-z_][A-Za-z0-9_-]*$/

/**
 * Rewrites a URI Template topic into the equivalent URL Pattern (`/books/{id}` -> `/books/:id`),
 * or returns null when the topic is not a plain named-variable template.
 *
 * URL Patterns have no equivalent for the RFC 6570 operators (`{?q}`, `{+var}`, `{/path}`...), and
 * rewriting only part of a topic would leave a pattern the hub rejects with a 400. Those topics are
 * reported as "not a pattern" so the caller can fall back to an exact match.
 */
export function toUrlPattern(topic: string): string | null {
    let supported = true

    const pattern = topic.replace(NAMED_PLACEHOLDER, (placeholder: string, name: string) => {
        if (!PLACEHOLDER_NAME.test(name)) {
            supported = false

            return placeholder
        }

        return `:${name}`
    })

    return supported && pattern !== topic ? pattern : null
}

/**
 * Appends the query parameter that selects `topic`, spelled for the protocol version the hub speaks.
 *
 * A URL Pattern group also matches the literal placeholder, so a templated topic subscribed to with
 * `match_urlpattern` receives updates published as `/api/books/{id}` (API Platform, and this
 * bundle's own publish path) as well as concrete ones like `/api/books/42`.
 */
function appendTopicMatcher(url: URL, topic: string, protocolVersion: string): void {
    if (LEGACY_PROTOCOL_VERSION === protocolVersion) {
        url.searchParams.append(LEGACY_TOPIC_PARAMETER, topic)

        return
    }

    const pattern = toUrlPattern(topic)

    if (null === pattern) {
        url.searchParams.append(EXACT_MATCH_PARAMETER, topic)

        return
    }

    url.searchParams.append(URL_PATTERN_MATCH_PARAMETER, pattern)
}

export function createMercureSubscription(
    config: MercureConfig,
    onMessage: (event: MessageEvent) => void
): EventSource {
    const url = new URL(config.hubUrl, window.location.href)
    const protocolVersion = config.protocolVersion || LEGACY_PROTOCOL_VERSION

    for (const topic of config.topics) {
        appendTopicMatcher(url, topic, protocolVersion)
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
