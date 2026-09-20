let registered = false;
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
        return instance.render(() => api.ajax.reload(null, true));
    });
}
//# sourceMappingURL=filterFeature.js.map