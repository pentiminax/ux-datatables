import type { DataTableView } from '../types/dataTableView.js';
export declare function applyFeatureLayout(payload: Pick<DataTableView, 'layout'>, feature: string, entry: Record<string, unknown>, fallback: {
    position: string;
    before?: string[];
}): void;
