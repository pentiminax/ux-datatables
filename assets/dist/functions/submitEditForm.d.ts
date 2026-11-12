type SubmitEditFormPayload = {
    dataTable: string;
    id: string;
    formData: Record<string, any>;
    csrfToken?: string;
    query?: Record<string, unknown>;
    url?: string;
};
type SubmitEditFormResponse = {
    success: boolean;
    html?: string;
    response: Response;
};
export declare function submitEditForm(payload: SubmitEditFormPayload): Promise<SubmitEditFormResponse>;
export {};
