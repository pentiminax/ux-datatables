export declare function deleteEntity({ dataTable, id, csrfToken, query, url, }: {
    dataTable: string;
    id: string;
    csrfToken?: string;
    query?: Record<string, unknown>;
    url?: string;
}): Promise<Response>;
