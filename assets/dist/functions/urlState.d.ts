import type { DataTableView } from '../types/dataTableView.js';
export interface UrlStateConfig {
    search: boolean;
    order: boolean;
    page: boolean;
    pageLength: boolean;
    prefix: string;
}
export interface OrderEntry {
    name: string;
    dir: 'asc' | 'desc';
}
export interface UrlStateSnapshot {
    search?: string;
    order?: OrderEntry | OrderEntry[];
    start?: number;
    pageLength?: number;
}
export declare function isUrlStateEnabled(payload: DataTableView): UrlStateConfig | null;
export declare function readUrlState(cfg: UrlStateConfig, search?: string): UrlStateSnapshot;
export declare function applyUrlStateToPayload(payload: DataTableView, snapshot: UrlStateSnapshot): void;
export declare function writeUrlState(cfg: UrlStateConfig, table: any): void;
