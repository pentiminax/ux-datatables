const LEGACY_PROTOCOL_VERSION = '0.x';
const LEGACY_TOPIC_PARAMETER = 'topic';
const EXACT_MATCH_PARAMETER = 'match';
const URL_PATTERN_MATCH_PARAMETER = 'match_urlpattern';
const PLACEHOLDER = /\{([^{}]*)\}/g;
const VARIABLE_SPEC = /^(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})|\.(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*(?::[1-9][0-9]{0,3}|\*)?$/;
function isSimpleVariableList(expression) {
    return expression.split(',').every((specifier) => VARIABLE_SPEC.test(specifier));
}
export function toUrlPattern(topic) {
    let index = 0;
    let supported = true;
    const pattern = topic.replace(PLACEHOLDER, (placeholder, expression) => {
        if (!isSimpleVariableList(expression)) {
            supported = false;
            return placeholder;
        }
        return `:p${index++}`;
    });
    return supported && pattern !== topic ? pattern : null;
}
function appendTopicMatcher(url, topic, protocolVersion) {
    if (LEGACY_PROTOCOL_VERSION === protocolVersion) {
        url.searchParams.append(LEGACY_TOPIC_PARAMETER, topic);
        return;
    }
    const pattern = toUrlPattern(topic);
    if (null === pattern) {
        url.searchParams.append(EXACT_MATCH_PARAMETER, topic);
        return;
    }
    url.searchParams.append(URL_PATTERN_MATCH_PARAMETER, pattern);
}
export function createMercureSubscription(config, onMessage) {
    const url = new URL(config.hubUrl, window.location.href);
    const protocolVersion = config.protocolVersion || LEGACY_PROTOCOL_VERSION;
    for (const topic of config.topics) {
        appendTopicMatcher(url, topic, protocolVersion);
    }
    const eventSource = new EventSource(url.toString(), {
        withCredentials: config.withCredentials ?? false,
    });
    const debounceMs = config.debounceMs ?? 500;
    let debounceTimer = null;
    eventSource.onmessage = (event) => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            onMessage(event);
            debounceTimer = null;
        }, debounceMs);
    };
    return eventSource;
}
//# sourceMappingURL=mercureSubscription.js.map