export function shiftOrderForSelectColumn(order) {
    if (!Array.isArray(order)) {
        return order;
    }
    return order.map((entry) => {
        if (Array.isArray(entry) && typeof entry[0] === 'number') {
            return [entry[0] + 1, ...entry.slice(1)];
        }
        if (isIndexedEntry(entry)) {
            return { ...entry, idx: entry.idx + 1 };
        }
        return entry;
    });
}
function isIndexedEntry(entry) {
    return (entry !== null &&
        typeof entry === 'object' &&
        typeof entry.idx === 'number');
}
//# sourceMappingURL=shiftOrderForSelectColumn.js.map