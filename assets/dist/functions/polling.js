const MODAL_SELECTOR = '[data-ux-datatables-modal]';
const SEARCH_INPUT_SELECTOR = 'input[type="search"], .dt-search input';
export function isPollingEnabled(payload) {
    return typeof payload?.pollInterval === 'number' && payload.pollInterval > 0;
}
export function startPolling(table, intervalSeconds, container) {
    const intervalMs = intervalSeconds * 1000;
    let inFlight = false;
    let lastReload = Date.now();
    const onRequestStart = () => {
        inFlight = true;
    };
    const onRequestEnd = () => {
        inFlight = false;
    };
    const isTypingInSearch = () => container?.contains(document.activeElement) === true &&
        document.activeElement?.matches(SEARCH_INPUT_SELECTOR) === true;
    const isPaused = () => document.visibilityState === 'hidden' ||
        inFlight ||
        document.querySelector(MODAL_SELECTOR) !== null ||
        isTypingInSearch();
    const tick = () => {
        if (isPaused()) {
            return;
        }
        lastReload = Date.now();
        table.ajax?.reload(null, false);
    };
    const onVisibilityChange = () => {
        if (document.visibilityState === 'visible' && Date.now() - lastReload >= intervalMs) {
            tick();
        }
    };
    table.on('preXhr.dt', onRequestStart);
    table.on('xhr.dt', onRequestEnd);
    document.addEventListener('visibilitychange', onVisibilityChange);
    const timer = setInterval(tick, intervalMs);
    return () => {
        clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        table.off('preXhr.dt', onRequestStart);
        table.off('xhr.dt', onRequestEnd);
    };
}
let hasWarnedPollingWithMercure = false;
export function ensurePolling(current, { table, payload, element, mercureEnabled }) {
    if (current) {
        return current;
    }
    if (!element.isConnected || !table?.ajax || !isPollingEnabled(payload)) {
        return null;
    }
    if (mercureEnabled && !hasWarnedPollingWithMercure) {
        hasWarnedPollingWithMercure = true;
        console.warn('[ux-datatables] poll() and mercure() are both enabled: Mercure already pushes updates, so polling adds redundant requests.');
    }
    return startPolling(table, payload.pollInterval, element.closest('.dt-container') ?? element);
}
//# sourceMappingURL=polling.js.map