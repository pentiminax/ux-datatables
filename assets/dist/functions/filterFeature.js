let registered = false;
function hasAjaxSource(settings, api) {
    return Boolean(settings?.ajax ||
        settings?.sAjaxSource ||
        settings?.oFeatures?.bServerSide ||
        (typeof api.ajax?.url === 'function' && Boolean(api.ajax.url())));
}
export function registerFilterFeature(DataTable) {
    if (registered) {
        return;
    }
    registered = true;
    DataTable.feature.register('filters', (settings, opts) => {
        const instance = opts?.instance;
        if (!instance) {
            return document.createElement('div');
        }
        const api = new DataTable.Api(settings);
        if (!hasAjaxSource(settings, api)) {
            console.warn('[ux-datatables] Filters require an Ajax data source; enable serverSide() to use them.');
            return document.createElement('div');
        }
        return instance.render(() => {
            api.ajax.reload(null, true);
            if (api.state && typeof api.state.save === 'function') {
                api.state.save();
            }
        });
    });
}
//# sourceMappingURL=filterFeature.js.map