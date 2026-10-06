export const JSON_HEADERS = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
}

export async function postRowRequest<T extends { success: boolean }>(
    url: string,
    headers: HeadersInit,
    body: unknown,
    fallback: T
): Promise<T & { response: Response }> {
    const response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body) })
    const parsed: T = await response.json().catch(() => fallback)

    return { ...parsed, response }
}
