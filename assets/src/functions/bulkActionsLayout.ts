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
    console.warn(
        'applyBulkActionsLayout() is deprecated and removed in 2.0. Use installBulkActionBar() instead.'
    )
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position })
}
