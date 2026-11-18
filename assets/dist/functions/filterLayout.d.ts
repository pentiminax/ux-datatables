import type { DataTableView } from '../types/dataTableView.js';
import type { FilterBar } from './filters.js';
export interface FilterLayoutEntry {
    filters: {
        instance: FilterBar;
    };
}
export declare function applyFilterLayout(payload: Pick<DataTableView, 'layout'>, instance: FilterBar): void;
