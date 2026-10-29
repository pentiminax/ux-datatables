const SAFE_URL = /^(?:\/(?![/\\])|https?:\/\/[^/?#\s]+)[^\u0000-\u001f\u007f\\]*$/i;
const INTERACTIVE = 'a, button, input, select, textarea, label, [role="button"], [data-action-type], .dt-select, .select-checkbox';
export function isSafeRecordUrl(url) {
    return typeof url === 'string' && SAFE_URL.test(url);
}
export function bindRecordClick(table) {
    table.addEventListener('click', (event) => {
        const target = event.target;
        if (event.defaultPrevented || event.button !== 0 || !(target instanceof Element)) {
            return;
        }
        const row = target.closest('tbody tr[data-record-url]');
        if (!row || !table.contains(row) || target.closest(INTERACTIVE)) {
            return;
        }
        const url = row.dataset.recordUrl;
        if (!isSafeRecordUrl(url) || window.getSelection()?.toString()) {
            return;
        }
        if (event.ctrlKey || event.metaKey) {
            window.open(url, '_blank', 'noopener');
            return;
        }
        window.location.assign(url);
    });
}
//# sourceMappingURL=recordUrl.js.map