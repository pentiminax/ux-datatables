import { applyFeatureLayout } from './featureLayout.js';
export function applyBulkActionsLayout(payload, instance, position = 'topEnd') {
    console.warn('applyBulkActionsLayout() is deprecated and removed in 2.0. Use installBulkActionBar() instead.');
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position });
}
//# sourceMappingURL=bulkActionsLayout.js.map