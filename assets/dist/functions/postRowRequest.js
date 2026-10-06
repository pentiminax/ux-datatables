export const JSON_HEADERS = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};
export async function postRowRequest(url, headers, body, fallback) {
    const response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body) });
    const parsed = await response.json().catch(() => fallback);
    return { ...parsed, response };
}
//# sourceMappingURL=postRowRequest.js.map