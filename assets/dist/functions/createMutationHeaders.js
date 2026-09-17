export function createMutationHeaders(csrfToken) {
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    return headers;
}
//# sourceMappingURL=createMutationHeaders.js.map