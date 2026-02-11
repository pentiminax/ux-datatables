type ToggleBooleanPayload = {
    id: string;
    entity: string;
    field: string;
    value: boolean;
    url: string;
    method?: string;
};

export async function toggleBooleanValue({ id, entity, field, value, url, method = 'PATCH' }: ToggleBooleanPayload): Promise<Response> {
    return await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ id, entity, field, value }),
    });
}
