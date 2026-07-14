export function createMutationHeaders(csrfToken?: string): Record<string, string> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }

    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken
    }

    return headers
}
