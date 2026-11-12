interface EmptyStateLabels {
    noResults?: string;
    noResultsHint?: string;
    resetFilters?: string;
}
interface EmptyStateConfig {
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
export declare function applyEmptyState(payload: Record<string, any>): void;
export declare function bindEmptyStateReset(table: HTMLElement, reset: () => void): void;
export {};
