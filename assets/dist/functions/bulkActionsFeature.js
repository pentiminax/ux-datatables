let registered = false;
export function registerBulkActionsFeature(DataTable) {
    if (registered) {
        return;
    }
    registered = true;
    DataTable.feature.register('bulkActions', (settings, opts) => {
        const instance = opts?.instance;
        if (!instance) {
            return document.createElement('div');
        }
        return instance.render(new DataTable.Api(settings));
    });
}
//# sourceMappingURL=bulkActionsFeature.js.map