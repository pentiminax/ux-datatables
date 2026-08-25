export function unwrapStaleDataTableMarkup(table) {
    const container = table.closest('.dt-container, .dataTables_wrapper');
    if (!container?.parentNode) {
        return;
    }
    container.parentNode.replaceChild(table, container);
}
//# sourceMappingURL=unwrapStaleDataTableMarkup.js.map