export type SummaryType = 'sum' | 'avg' | 'min' | 'max' | 'count';
export declare function hasSummaries(payload: Record<string, any>): boolean;
export declare function aggregate(type: SummaryType, values: unknown[]): number | null;
export declare function ensureSummaryFooter(tableElement: HTMLElement, payload: Record<string, any>): boolean;
export declare function installColumnSummaries(table: any, payload: Record<string, any>): void;
