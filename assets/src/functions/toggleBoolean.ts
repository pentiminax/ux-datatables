type ToggleBooleanPayload = {
    id: string;
    field: string;
    value: boolean;
    url: string;
    method?: string;
};

export async function toggleBooleanValue({ id, field, value, url, method = 'PATCH' }: ToggleBooleanPayload): Promise<Response> {
    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id, field, value }),
    });
}
