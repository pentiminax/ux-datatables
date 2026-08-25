// Turbo Drive caches a snapshot of the page's DOM before navigating away. If a DataTable is
// still mounted (wrapped in `div.dt-container`/`.dataTables_wrapper` with generated controls)
// at that moment, the snapshot is "dirty". Restoring it on Back/Forward reconnects a fresh
// Stimulus controller onto an already-wrapped table, and `DataTable.isDataTable()` returns
// false for it because DataTables' internal registry still tracks the original detached node —
// so the table gets initialized a second time, duplicating the wrapper and controls. This
// unwraps any such leftover markup before init, as a safety net for snapshots already cached
// before the fix that destroys the DataTable instance on `turbo:before-cache`.
export function unwrapStaleDataTableMarkup(table: HTMLTableElement): void {
    const container = table.closest('.dt-container, .dataTables_wrapper')

    if (!container?.parentNode) {
        return
    }

    container.parentNode.replaceChild(table, container)
}
