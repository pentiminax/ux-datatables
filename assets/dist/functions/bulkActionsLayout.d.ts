import type { BulkActionBar } from '../bulk/BulkActionBar.js';
export interface BulkActionsLayoutEntry {
    bulkActions: {
        instance: BulkActionBar;
    };
}
export declare function applyBulkActionsLayout(payload: Record<string, any>, instance: BulkActionBar, position?: string): void;
