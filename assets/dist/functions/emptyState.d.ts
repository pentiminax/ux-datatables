import type { DataTableView } from '../types/dataTableView.js';
interface EmptyStateLabels {
    noResults?: string;
    noResultsHint?: string;
    resetFilters?: string;
}
export interface EmptyStateConfig {
    heading: string;
    description?: string | null;
    icon?: string | null;
    action?: {
        label: string;
        url: string;
    } | null;
    labels?: EmptyStateLabels;
}
export declare function buildEmptyHtml(config: EmptyStateConfig): string;
export declare function buildNoResultsHtml(config: EmptyStateConfig, hasFilters: boolean): string;
export declare function applyEmptyState(payload: DataTableView): void;
export declare function bindEmptyStateReset(table: HTMLElement, reset: () => void): void;
export {};
