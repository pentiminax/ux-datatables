import type { BulkActionBar } from '../bulk/BulkActionBar.js'
import { applyFeatureLayout } from './featureLayout.js'

export interface BulkActionsLayoutEntry {
    bulkActions: { instance: BulkActionBar }
}

/**
 * @deprecated since 1.2, use installBulkActionBar() from '../bulk/BulkActionBar.js' instead.
 * Removed in 2.0.
 */
export function applyBulkActionsLayout(
    payload: Record<string, any>,
    instance: BulkActionBar,
    position = 'topEnd'
): void {
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position })
}
