import type { DataTableView } from '../types/dataTableView.js';
export type RowActionName = 'delete' | 'toggle' | 'detail' | 'editForm' | 'editFormSubmit';
export declare function rowActionUrl(payload: DataTableView, name: RowActionName): string;
