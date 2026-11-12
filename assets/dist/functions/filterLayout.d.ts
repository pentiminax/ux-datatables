import type { FilterBar } from './filters.js';
export interface FilterLayoutEntry {
    filters: {
        instance: FilterBar;
    };
}
export declare function applyFilterLayout(payload: Record<string, any>, instance: FilterBar): void;
