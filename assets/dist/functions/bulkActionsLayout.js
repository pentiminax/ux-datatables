import { applyFeatureLayout } from './featureLayout.js';
export function applyBulkActionsLayout(payload, instance, position = 'topStart') {
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position });
}
//# sourceMappingURL=bulkActionsLayout.js.map