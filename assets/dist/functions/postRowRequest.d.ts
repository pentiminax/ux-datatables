export declare const JSON_HEADERS: {
    'Content-Type': string;
    'X-Requested-With': string;
};
export declare function postRowRequest<T extends {
    success: boolean;
}>(url: string, headers: HeadersInit, body: unknown, fallback: T): Promise<T & {
    response: Response;
}>;
