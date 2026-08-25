export function unwrapStaleDataTableMarkup(table) {
    const container = table.parentElement;
    if (!container?.matches('.dt-container, .dataTables_wrapper') || !container.parentNode) {
        return;
    }
    container.parentNode.replaceChild(table, container);
}
//# sourceMappingURL=unwrapStaleDataTableMarkup.js.map