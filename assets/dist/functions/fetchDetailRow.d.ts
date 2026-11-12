type FetchDetailRowPayload = {
    dataTable: string;
    id: string;
    query?: Record<string, unknown>;
    url?: string;
};
type FetchDetailRowResponse = {
    success: boolean;
    html: string;
    response: Response;
};
export declare function fetchDetailRow({ url, ...payload }: FetchDetailRowPayload): Promise<FetchDetailRowResponse>;
export {};
