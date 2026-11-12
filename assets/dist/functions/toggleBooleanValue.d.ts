type ToggleBooleanPayload = {
    id: string;
    rowId?: string;
    field: string;
    newValue: boolean;
    url: string;
    method?: string;
    dataTable: string;
    csrfToken?: string;
    query?: Record<string, unknown>;
};
export declare function toggleBooleanValue({ id, rowId, field, newValue, url, method, dataTable, csrfToken, query, }: ToggleBooleanPayload): Promise<Response>;
export {};
