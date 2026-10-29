// biome-ignore lint/suspicious/noControlCharactersInRegex: control characters are what this rejects
const SAFE_URL = /^(?:\/(?![/\\])|https?:\/\/[^/?#\s]+)[^\s\u0000-\u001f\u007f\\]*$/i

const INTERACTIVE =
    'a, button, input, select, textarea, label, [role="button"], [data-action-type], .dt-select, .select-checkbox, .dtr-control, .dt-control'

/**
 * Accepts only a path starting with a single "/" or an http(s) URL. Mirrors the PHP
 * `RecordUrlFilter`, so a value that reaches the DOM by another route is checked again here.
 */
export function isSafeRecordUrl(url: unknown): url is string {
    return typeof url === 'string' && SAFE_URL.test(url)
}

/**
 * Navigates to the row's `data-record-url` on click. Clicks on interactive content, row actions and
 * the bulk selection cell keep their own behavior. The row stays non-focusable: keep a real link in
 * a cell for keyboard and assistive technology users.
 */
export function bindRecordClick(table: HTMLElement): void {
    table.addEventListener('click', (event: MouseEvent): void => {
        const target = event.target

        if (event.defaultPrevented || event.button !== 0 || !(target instanceof Element)) {
            return
        }

        const row = target.closest<HTMLElement>('tbody tr[data-record-url]')

        if (!row || !table.contains(row) || target.closest(INTERACTIVE)) {
            return
        }

        const url = row.dataset.recordUrl

        if (!isSafeRecordUrl(url) || window.getSelection()?.toString()) {
            return
        }

        if (event.ctrlKey || event.metaKey) {
            window.open(url, '_blank', 'noopener')

            return
        }

        window.location.assign(url)
    })
}
