interface ActionParams {
    url: string;
    id: string;
    action: string;
}

export const performAction = async ({ url, id, action }: ActionParams): Promise<Response> => {
    const requestOptions: RequestInit = {
        method: action.toUpperCase(),
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (action.toUpperCase() !== 'GET') {
        requestOptions.body = JSON.stringify({ id });
    }

    const finalUrl = action.toUpperCase() === 'DELETE' ? `${url}/${id}` : url;

    return await fetch(finalUrl, requestOptions);
}