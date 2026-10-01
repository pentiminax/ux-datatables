import type { BulkActionBar } from '../bulk/BulkActionBar.js'
import { applyFeatureLayout } from './featureLayout.js'

export interface BulkActionsLayoutEntry {
    bulkActions: { instance: BulkActionBar }
}

/**
 * @deprecated since v1.1.1: `installBulkActionBar()` from `../bulk/BulkActionBar.js` places the
 * bar. Kept for deep imports of `dist/functions/bulkActionsLayout.js`; it will be removed in v2.0.
 */
export function applyBulkActionsLayout(
    payload: Record<string, any>,
    instance: BulkActionBar,
    position = 'topEnd'
): void {
    applyFeatureLayout(payload, 'bulkActions', { bulkActions: { instance } }, { position })
}
