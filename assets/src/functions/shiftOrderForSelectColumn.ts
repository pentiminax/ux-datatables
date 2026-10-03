/**
 * Move the numeric column indexes of an initial `order` one column to the right.
 *
 * Checkbox selection prepends a column on the client, so an index written against the server
 * column list would otherwise sort the neighbouring column. Name-based entries are untouched.
 */
export function shiftOrderForSelectColumn(order: unknown): unknown {
    if (!Array.isArray(order)) {
        return order
    }

    return order.map((entry) => {
        if (Array.isArray(entry) && typeof entry[0] === 'number') {
            return [entry[0] + 1, ...entry.slice(1)]
        }

        if (isIndexedEntry(entry)) {
            return { ...entry, idx: entry.idx + 1 }
        }

        return entry
    })
}

function isIndexedEntry(entry: unknown): entry is { idx: number } {
    return (
        entry !== null &&
        typeof entry === 'object' &&
        typeof (entry as { idx?: unknown }).idx === 'number'
    )
}
