export function applyFeatureLayout(payload, feature, entry, fallback) {
    payload.layout ??= {};
    const layout = payload.layout;
    if (replaceMarker(layout, feature, entry)) {
        return;
    }
    const cell = layout[fallback.position];
    if (cell === undefined || cell === null) {
        layout[fallback.position] = [...(fallback.before ?? []), entry];
    }
    else if (Array.isArray(cell)) {
        layout[fallback.position] = [...cell, entry];
    }
    else {
        layout[fallback.position] = [cell, entry];
    }
}
function isMarker(value, feature) {
    if (value === feature)
        return true;
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return feature in value;
    }
    return false;
}
function replaceMarker(layout, feature, entry) {
    let replaced = false;
    for (const key of Object.keys(layout)) {
        const value = layout[key];
        if (isMarker(value, feature)) {
            layout[key] = entry;
            replaced = true;
            continue;
        }
        if (Array.isArray(value)) {
            layout[key] = value.map((item) => {
                if (isMarker(item, feature)) {
                    replaced = true;
                    return entry;
                }
                return item;
            });
        }
    }
    return replaced;
}
//# sourceMappingURL=featureLayout.js.map