import type { DataTableView } from '../types/dataTableView.js';
export declare const DEFAULT_ROW_ACTION_URLS: {
    delete: string;
    toggle: string;
    detail: string;
    editForm: string;
    editFormSubmit: string;
};
export type RowActionName = keyof typeof DEFAULT_ROW_ACTION_URLS;
export declare function rowActionUrl(payload: DataTableView, name: RowActionName): string;
