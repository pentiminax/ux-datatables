interface Resettable {
    resetFilters: () => void;
}
interface SearchableTable {
    search: (value: string) => unknown;
    columns?: () => {
        search: (value: string) => unknown;
    };
}
export declare function applyTableLanguage(payload: Record<string, any>): Promise<void>;
export declare function bindRowFeatures(payload: Record<string, any>, element: HTMLElement, getTable: () => SearchableTable | null, filterBar: Resettable | null): void;
export {};
