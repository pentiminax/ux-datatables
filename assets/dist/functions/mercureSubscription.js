export function createMercureSubscription(config, onMessage) {
    const url = new URL(config.hubUrl, window.location.href);
    url.searchParams.append('topic', config.topic);
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