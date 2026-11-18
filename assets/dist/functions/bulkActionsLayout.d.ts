import type { BulkActionBar } from '../bulk/BulkActionBar.js';
import type { DataTableView } from '../types/dataTableView.js';
export interface BulkActionsLayoutEntry {
    bulkActions: {
        instance: BulkActionBar;
    };
}
export declare function applyBulkActionsLayout(payload: Pick<DataTableView, 'layout'>, instance: BulkActionBar, position?: string): void;
