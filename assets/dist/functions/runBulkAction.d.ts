export interface BulkActionRequest {
    url: string;
    dataTable: string;
    action: string;
    ids: string[];
    allMatching?: boolean;
    deselectedIds?: string[];
    query?: Record<string, unknown>;
    csrfToken?: string;
}
export interface BulkActionResult {
    success: boolean;
    processed: number;
    skipped: number;
    message?: string;
}
export declare function runBulkAction({ url, dataTable, action, ids, allMatching, deselectedIds, query, csrfToken, }: BulkActionRequest): Promise<BulkActionResult>;
