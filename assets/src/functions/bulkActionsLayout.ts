import type { BulkActionBar } from '../bulk/BulkActionBar.js'
import { applyFeatureLayout } from './featureLayout.js'

export interface BulkActionsLayoutEntry {
    bulkActions: { instance: BulkActionBar }
}

export function applyBulkActionsLayout(
    payload: Record<string, any>,
    instance: BulkActionBar,
    position = 'topStart'
): void {
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position })
}
