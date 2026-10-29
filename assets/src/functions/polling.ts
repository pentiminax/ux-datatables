export interface PollingTable {
    ajax?: { reload: (callback?: (() => void) | null, resetPaging?: boolean) => void }
    on: (event: string, callback: (...args: any[]) => void) => unknown
    off: (event: string, callback?: (...args: any[]) => void) => unknown
}

const MODAL_SELECTOR = '[data-ux-datatables-modal]'
const SEARCH_INPUT_SELECTOR = 'input[type="search"], .dt-search input'

export function isPollingEnabled(payload: Record<string, any>): boolean {
    return typeof payload?.pollInterval === 'number' && payload.pollInterval > 0
}

/**
 * Reloads the Ajax source every `intervalSeconds` without resetting paging, search or order.
 * Ticks are skipped while the tab is hidden, a modal is open, a request is in flight or the viewer
 * is typing in the search field. Returns a function that stops everything.
 */
export function startPolling(
    table: PollingTable,
    intervalSeconds: number,
    container: HTMLElement | null
): () => void {
    const intervalMs = intervalSeconds * 1000
    let inFlight = false
    let lastReload = Date.now()

    const onRequestStart = (): void => {
        inFlight = true
    }
    const onRequestEnd = (): void => {
        inFlight = false
    }

    const isTypingInSearch = (): boolean =>
        container?.contains(document.activeElement) === true &&
        document.activeElement?.matches(SEARCH_INPUT_SELECTOR) === true

    const isPaused = (): boolean =>
        document.visibilityState === 'hidden' ||
        inFlight ||
        document.querySelector(MODAL_SELECTOR) !== null ||
        isTypingInSearch()

    const tick = (): void => {
        if (isPaused()) {
            return
        }

        lastReload = Date.now()
        table.ajax?.reload(null, false)
    }

    const onVisibilityChange = (): void => {
        if (document.visibilityState === 'visible' && Date.now() - lastReload >= intervalMs) {
            tick()
        }
    }

    table.on('preXhr.dt', onRequestStart)
    table.on('xhr.dt', onRequestEnd)
    document.addEventListener('visibilitychange', onVisibilityChange)
    const timer = setInterval(tick, intervalMs)

    return () => {
        clearInterval(timer)
        document.removeEventListener('visibilitychange', onVisibilityChange)
        table.off('preXhr.dt', onRequestStart)
        table.off('xhr.dt', onRequestEnd)
    }
}

let hasWarnedPollingWithMercure = false

interface EnsurePollingOptions {
    table: PollingTable | null
    payload: Record<string, any>
    element: HTMLElement
    mercureEnabled: boolean
}

/**
 * Starts polling unless it already runs, the element is detached or the table has no Ajax source.
 * Returns the function that stops it, or null when nothing runs.
 */
export function ensurePolling(
    current: (() => void) | null,
    { table, payload, element, mercureEnabled }: EnsurePollingOptions
): (() => void) | null {
    if (current) {
        return current
    }

    if (!element.isConnected || !table?.ajax || !isPollingEnabled(payload)) {
        return null
    }

    if (mercureEnabled && !hasWarnedPollingWithMercure) {
        hasWarnedPollingWithMercure = true
        console.warn(
            '[ux-datatables] poll() and mercure() are both enabled: Mercure already pushes updates, so polling adds redundant requests.'
        )
    }

    return startPolling(
        table,
        payload.pollInterval,
        element.closest<HTMLElement>('.dt-container') ?? element
    )
}
