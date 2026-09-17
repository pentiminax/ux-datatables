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

/** A `{placeholder}` in a topic, i.e. one RFC 6570 expression. */
const PLACEHOLDER = /\{([^{}]*)\}/g

/**
 * One RFC 6570 variable specifier: a variable name (dot-separated varchars, `ALPHA / DIGIT / "_" /
 * pct-encoded`) with the optional level-4 modifier (`:3` prefix length or `*` explode).
 */
const VARIABLE_SPEC =
    /^(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})|\.(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?$/

/**
 * Whether an expression is a comma-separated list of variable specifiers, which is the only URI
 * Template shape that expands inside a single path segment. Anything with an operator (`{?q}`,
 * `{+path}`, `{/segments}`) changes the shape of the URL and has no URL Pattern equivalent.
 */
function isSimpleVariableList(expression: string): boolean {
    return expression.split(',').every((specifier) => VARIABLE_SPEC.test(specifier))
}

/**
 * Rewrites a URI Template topic into the equivalent URL Pattern (`/books/{id}` -> `/books/:p0`),
 * or returns null when the topic is not a template of simple variable expressions.
 *
 * Group names are generated rather than derived from the variable names. RFC 6570 variable names are
 * not all valid URL Pattern group names — `{book.id}` parses as something else entirely, `{1}`
 * throws — and a repeated variable would re-use a group name, which URL Patterns reject. Nothing
 * reads the group names back, so a fresh one per expression is both correct and safe.
 */
export function toUrlPattern(topic: string): string | null {
    let index = 0
    let supported = true

    const pattern = topic.replace(PLACEHOLDER, (placeholder: string, expression: string) => {
        if (!isSimpleVariableList(expression)) {
            supported = false

            return placeholder
        }

        return `:p${index++}`
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
