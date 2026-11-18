import type { DataTableView } from '../types/dataTableView.js';
interface Resettable {
    resetFilters: () => void;
}
interface SearchableTable {
    search: (value: string) => unknown;
    columns?: () => {
        search: (value: string) => unknown;
    };
}
export declare function applyTableLanguage(payload: DataTableView): Promise<void>;
export declare function bindRowFeatures(payload: DataTableView, element: HTMLElement, getTable: () => SearchableTable | null, filterBar: Resettable | null): void;
export {};
