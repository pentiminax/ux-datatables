type FetchEditFormPayload = {
    dataTable: string;
    id: string;
    query?: Record<string, unknown>;
    url?: string;
};
type FetchEditFormResponse = {
    success: boolean;
    html: string;
    response: Response;
};
export declare function fetchEditForm({ url, ...payload }: FetchEditFormPayload): Promise<FetchEditFormResponse>;
export {};
