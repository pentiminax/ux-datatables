/**
 * Put a feature instance where its marker sits in the DataTables `layout` option.
 *
 * The marker is the plain feature name the server wrote into the layout; when the user never
 * placed one, the feature falls back to its default cell without dropping what already sits there.
 */
export function applyFeatureLayout(
    payload: Record<string, any>,
    feature: string,
    entry: Record<string, unknown>,
    fallback: { position: string; before?: string[] }
): void {
    payload.layout ??= {}
    const layout = payload.layout as Record<string, unknown>

    if (replaceMarker(layout, feature, entry)) {
        return
    }

    const cell = layout[fallback.position]

    if (cell === undefined || cell === null) {
        layout[fallback.position] = [...(fallback.before ?? []), entry]
    } else if (Array.isArray(cell)) {
        layout[fallback.position] = [...cell, entry]
    } else {
        layout[fallback.position] = [cell, entry]
    }
}

function isMarker(value: unknown, feature: string): boolean {
    if (value === feature) return true
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return feature in (value as Record<string, unknown>)
    }
    return false
}

function replaceMarker(
    layout: Record<string, unknown>,
    feature: string,
    entry: Record<string, unknown>
): boolean {
    let replaced = false

    for (const key of Object.keys(layout)) {
        const value = layout[key]

        if (isMarker(value, feature)) {
            layout[key] = entry
            replaced = true
            continue
        }

        if (Array.isArray(value)) {
            layout[key] = value.map((item) => {
                if (isMarker(item, feature)) {
                    replaced = true
                    return entry
                }
                return item
            })
        }
    }

    return replaced
}
