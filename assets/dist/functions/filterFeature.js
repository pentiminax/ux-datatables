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
        return instance.render(() => {
            if (api.ajax) {
                api.ajax.reload(null, true);
            }
            else {
                api.draw();
            }
            if (api.state && typeof api.state.save === 'function') {
                api.state.save();
            }
        });
    });
}
//# sourceMappingURL=filterFeature.js.map