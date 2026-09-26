let registered = false;
function hasAjaxSource(settings) {
    return Boolean(settings?.ajax || settings?.oFeatures?.bServerSide);
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
        if (!hasAjaxSource(settings)) {
            console.warn('[ux-datatables] Filters require an Ajax source; enable serverSide() to use them.');
            return document.createElement('div');
        }
        const api = new DataTable.Api(settings);
        return instance.render(() => api.ajax.reload(null, true));
    });
}
//# sourceMappingURL=filterFeature.js.map