let registered = false;
export function matchesClientFilters(settings, filterBar, searchData, rowData) {
    const applied = filterBar.collectValues();
    if (!applied || Object.keys(applied).length === 0) {
        return true;
    }
    const definitions = filterBar.getDefinitions();
    const defsByName = new Map(definitions.map((d) => [d.name, d]));
    const columns = settings?.aoColumns ?? [];
    for (const [name, val] of Object.entries(applied)) {
        if (val === null || val === undefined || val === '') {
            continue;
        }
        const def = defsByName.get(name);
        const colIndex = columns.findIndex((col) => col.sName === name || col.name === name || col.data === name || col.mData === name);
        const hasRawProperty = rowData && typeof rowData === 'object' && !Array.isArray(rowData) && name in rowData;
        const hasColData = colIndex !== -1 &&
            rowData &&
            typeof rowData === 'object' &&
            !Array.isArray(rowData) &&
            columns[colIndex].data in rowData;
        if (colIndex === -1 && !hasRawProperty) {
            continue;
        }
        let rawVal = undefined;
        if (hasRawProperty) {
            rawVal = rowData[name];
        }
        else if (hasColData) {
            rawVal = rowData[columns[colIndex].data];
        }
        else if (Array.isArray(rowData) && colIndex !== -1) {
            rawVal = rowData[colIndex];
        }
        const renderedText = colIndex !== -1 && searchData?.[colIndex] !== undefined
            ? String(searchData[colIndex]).trim()
            : '';
        const type = def?.type ?? 'text';
        if (type === 'checkbox') {
            continue;
        }
        if (type === 'text') {
            const searchStr = String(val).trim().toLowerCase();
            if (!searchStr)
                continue;
            const rowStr = rawVal !== undefined && rawVal !== null
                ? String(rawVal).toLowerCase()
                : renderedText.toLowerCase();
            if (!rowStr.includes(searchStr) && !renderedText.toLowerCase().includes(searchStr)) {
                return false;
            }
        }
        else if (type === 'select') {
            const options = def?.options ?? {};
            const hasRaw = rawVal !== undefined && rawVal !== null && String(rawVal).trim() !== '';
            if (def?.multiple && Array.isArray(val)) {
                if (val.length === 0)
                    continue;
                const selectedStrings = val.map(String);
                if (hasRaw) {
                    if (Array.isArray(rawVal)) {
                        const rawStrings = rawVal.map(String);
                        if (!selectedStrings.some((s) => rawStrings.includes(s))) {
                            return false;
                        }
                    }
                    else if (!selectedStrings.includes(String(rawVal).trim())) {
                        return false;
                    }
                }
                else if (renderedText !== '') {
                    const expectedLabels = selectedStrings.map((s) => options[s] ?? s);
                    if (!expectedLabels.includes(renderedText)) {
                        return false;
                    }
                }
            }
            else {
                const selStr = String(val).trim();
                if (!selStr)
                    continue;
                if (hasRaw) {
                    if (String(rawVal).trim() !== selStr) {
                        return false;
                    }
                }
                else if (renderedText !== '') {
                    const expectedLabel = options[selStr] ?? selStr;
                    if (renderedText !== expectedLabel) {
                        return false;
                    }
                }
            }
        }
        else if (type === 'ternary') {
            const norm = String(val).toLowerCase().trim();
            const isTrue = norm === '1' || norm === 'true' || norm === 'yes';
            const isFalse = norm === '0' || norm === 'false' || norm === 'no';
            const isNull = rawVal === null ||
                rawVal === undefined ||
                (typeof rawVal === 'string' && rawVal.trim() === '') ||
                (rawVal === undefined && renderedText === '');
            if (isTrue && isNull)
                return false;
            if (isFalse && !isNull)
                return false;
        }
        else if (type === 'dateRange') {
            if (typeof val === 'object' && val !== null) {
                const { from, to } = val;
                const dateTarget = rawVal !== undefined && rawVal !== null && rawVal !== ''
                    ? String(rawVal)
                    : renderedText;
                if (!dateTarget)
                    return false;
                const rowTime = parseDateComparable(dateTarget);
                if (rowTime === null)
                    return false;
                if (from) {
                    const fromTime = parseDateComparable(from);
                    if (fromTime !== null && rowTime < fromTime) {
                        return false;
                    }
                }
                if (to) {
                    const toTime = parseDateUpperBound(to);
                    if (toTime !== null && rowTime > toTime) {
                        return false;
                    }
                }
            }
        }
    }
    return true;
}
function parseDateComparable(dateStr) {
    if (!dateStr)
        return null;
    const match = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/.exec(dateStr.trim());
    if (match) {
        const year = parseInt(match[1], 10);
        const month = parseInt(match[2], 10) - 1;
        const day = parseInt(match[3], 10);
        return Date.UTC(year, month, day);
    }
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? null : d.getTime();
}
function parseDateUpperBound(toStr) {
    if (!toStr)
        return null;
    const match = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/.exec(toStr.trim());
    if (match) {
        const year = parseInt(match[1], 10);
        const month = parseInt(match[2], 10) - 1;
        const day = parseInt(match[3], 10);
        return Date.UTC(year, month, day, 23, 59, 59, 999);
    }
    const d = new Date(toStr);
    return isNaN(d.getTime()) ? null : d.getTime();
}
export function registerFilterFeature(DataTable) {
    if (registered) {
        return;
    }
    registered = true;
    if (DataTable.ext && Array.isArray(DataTable.ext.search)) {
        DataTable.ext.search.push((settings, searchData, dataIndex, rowData) => {
            const filterBar = settings?._uxFilterBar;
            if (!filterBar) {
                return true;
            }
            if (settings?.oFeatures?.bServerSide || settings?.bServerSide) {
                return true;
            }
            return matchesClientFilters(settings, filterBar, searchData, rowData);
        });
    }
    DataTable.feature.register('filters', (settings, opts) => {
        const instance = opts?.instance;
        if (!instance) {
            return document.createElement('div');
        }
        if (settings) {
            settings._uxFilterBar = instance;
        }
        const api = new DataTable.Api(settings);
        return instance.render(() => {
            const hasAjax = Boolean(settings?.ajax ||
                settings?.sAjaxSource ||
                settings?.oFeatures?.bServerSide ||
                (typeof api.ajax?.url === 'function' && Boolean(api.ajax.url())) ||
                (api.ajax && typeof api.ajax.reload === 'function' && typeof api.draw !== 'function'));
            if (hasAjax && api.ajax && typeof api.ajax.reload === 'function') {
                api.ajax.reload(null, true);
            }
            else if (typeof api.draw === 'function') {
                api.draw();
            }
            if (api.state && typeof api.state.save === 'function') {
                api.state.save();
            }
        });
    });
}
//# sourceMappingURL=filterFeature.js.map