const FEATURE = 'filters';
function isFiltersMarker(value) {
    if (value === FEATURE)
        return true;
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return FEATURE in value;
    }
    return false;
}
function replaceMarker(layout, entry) {
    let replaced = false;
    for (const key of Object.keys(layout)) {
        const value = layout[key];
        if (isFiltersMarker(value)) {
            layout[key] = entry;
            replaced = true;
            continue;
        }
        if (Array.isArray(value)) {
            layout[key] = value.map((item) => {
                if (isFiltersMarker(item)) {
                    replaced = true;
                    return entry;
                }
                return item;
            });
        }
    }
    return replaced;
}
export function applyFilterLayout(payload, instance) {
    const entry = { filters: { instance } };
    const layout = (payload.layout ??= {});
    if (replaceMarker(layout, entry)) {
        return;
    }
    const topEnd = layout.topEnd;
    if (topEnd === undefined || topEnd === null) {
        layout.topEnd = ['search', entry];
    }
    else if (Array.isArray(topEnd)) {
        layout.topEnd = [...topEnd, entry];
    }
    else {
        layout.topEnd = [topEnd, entry];
    }
}
//# sourceMappingURL=filterLayout.js.map