import type { DataTableView } from '../types/dataTableView.js';
export type SummaryType = 'sum' | 'avg' | 'min' | 'max' | 'count';
export declare function hasSummaries(payload: DataTableView): boolean;
export declare function aggregate(type: SummaryType, values: unknown[]): number | null;
export declare function ensureSummaryFooter(tableElement: HTMLElement, payload: DataTableView): boolean;
export declare function installColumnSummaries(table: any, payload: DataTableView): void;
