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
        let rawVal = undefined;
        if (rowData && typeof rowData === 'object' && !Array.isArray(rowData)) {
            rawVal =
                rowData[name] ??
                    (colIndex !== -1 && columns[colIndex].data ? rowData[columns[colIndex].data] : undefined);
        }
        else if (Array.isArray(rowData) && colIndex !== -1) {
            rawVal = rowData[colIndex];
        }
        const renderedText = colIndex !== -1 && searchData?.[colIndex] !== undefined
            ? String(searchData[colIndex]).trim()
            : String(rawVal ?? '').trim();
        if (rawVal === undefined) {
            rawVal = renderedText;
        }
        const type = def?.type ?? 'text';
        if (type === 'text') {
            const searchStr = String(val).trim().toLowerCase();
            if (!searchStr)
                continue;
            const rowStr = String(rawVal).toLowerCase();
            const rendStr = renderedText.toLowerCase();
            if (!rowStr.includes(searchStr) && !rendStr.includes(searchStr)) {
                return false;
            }
        }
        else if (type === 'select') {
            const options = def?.options ?? {};
            if (def?.multiple && Array.isArray(val)) {
                if (val.length === 0)
                    continue;
                const rowStr = String(rawVal).trim();
                const matches = val.some((v) => {
                    const strV = String(v);
                    const label = options[strV];
                    return (strV === rowStr ||
                        (label !== undefined && (label === renderedText || label === rowStr)) ||
                        (Array.isArray(rawVal) && rawVal.map(String).includes(strV)));
                });
                if (!matches)
                    return false;
            }
            else {
                const selVal = String(val).trim();
                if (!selVal)
                    continue;
                const rowStr = String(rawVal).trim();
                const label = options[selVal];
                const matches = selVal === rowStr ||
                    (label !== undefined && (label === renderedText || label === rowStr));
                if (!matches)
                    return false;
            }
        }
        else if (type === 'ternary') {
            const norm = String(val).toLowerCase().trim();
            const isTrue = norm === '1' || norm === 'true' || norm === 'yes';
            const isFalse = norm === '0' || norm === 'false' || norm === 'no';
            const hasValue = rawVal !== null &&
                rawVal !== undefined &&
                rawVal !== '' &&
                rawVal !== false &&
                rawVal !== 0 &&
                rawVal !== '0' &&
                renderedText !== '' &&
                renderedText !== '0' &&
                renderedText !== 'No' &&
                renderedText !== 'Non';
            if (isTrue && !hasValue)
                return false;
            if (isFalse && hasValue)
                return false;
        }
        else if (type === 'dateRange') {
            if (typeof val === 'object' && val !== null) {
                const { from, to } = val;
                const dateStr = String(rawVal || renderedText).trim();
                if (!dateStr)
                    return false;
                const rowDate = new Date(dateStr);
                if (isNaN(rowDate.getTime()))
                    return false;
                if (from) {
                    const fromDate = new Date(from);
                    if (!isNaN(fromDate.getTime()) && rowDate < fromDate) {
                        return false;
                    }
                }
                if (to) {
                    const toDate = new Date(to);
                    if (!isNaN(toDate.getTime())) {
                        if (/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/.test(to)) {
                            toDate.setHours(23, 59, 59, 999);
                        }
                        if (rowDate > toDate) {
                            return false;
                        }
                    }
                }
            }
        }
        else if (type === 'checkbox') {
            if (Boolean(val) && !Boolean(rawVal)) {
                return false;
            }
        }
    }
    return true;
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