export function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

export function parseBooleanValue(value: unknown, defaultValue: boolean = false): boolean {
    if (null === value || undefined === value || '' === value) {
        return defaultValue
    }

    if (typeof value === 'boolean') {
        return value
    }

    if (typeof value === 'number') {
        return value !== 0
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase()
        return ['1', 'true', 'yes', 'y', 'on'].includes(normalized)
    }

    return false
}

export function isUnsafeUrl(url: string): boolean {
    const trimmed = url.trim().toLowerCase()
    return trimmed.startsWith('javascript:') || trimmed.startsWith('data:')
}
