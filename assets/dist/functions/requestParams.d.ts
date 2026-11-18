import type { DataTableView } from '../types/dataTableView.js';
export type RequestParams = Record<string, any>;
export interface RequestParamsOptions {
    filters?: () => Record<string, unknown>;
    transport?: (params: RequestParams) => Record<string, string>;
}
export interface RequestParamsHandle {
    current(): RequestParams;
}
export declare function installRequestParams(payload: DataTableView, options?: RequestParamsOptions): RequestParamsHandle;
