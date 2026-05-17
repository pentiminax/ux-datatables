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
    const normalized = url.replace(/[\u0000-\u0020]+/g, '').toLowerCase()

    return (
        normalized.startsWith('javascript:') ||
        normalized.startsWith('data:') ||
        normalized.startsWith('vbscript:') ||
        normalized.startsWith('file:')
    )
}

export function getUrlProtocol(url: string): string | null {
    const normalized = url.trimStart()
    const matches = normalized.match(/^([a-z][a-z0-9+.-]*):/i)

    return matches?.[1].toLowerCase() ?? null
}

export function withDefaultProtocol(url: string, defaultProtocol?: string): string {
    if (!defaultProtocol || getUrlProtocol(url) || url.startsWith('/') || url.startsWith('#')) {
        return url
    }

    return `${defaultProtocol.replace(/:$/, '')}://${url}`
}

export function isAllowedUrlProtocol(url: string, allowedProtocols?: string[]): boolean {
    if (!allowedProtocols) {
        return true
    }

    const normalizedUrl = url.trimStart()

    // Same-origin links (fragments and root-relative paths) carry no protocol;
    // protocol-relative "//host" is excluded so it stays subject to the allowlist.
    if (
        normalizedUrl.startsWith('#') ||
        (normalizedUrl.startsWith('/') && !normalizedUrl.startsWith('//'))
    ) {
        return true
    }

    const protocol = getUrlProtocol(normalizedUrl)
    if (!protocol) {
        return false
    }

    return allowedProtocols
        .map((allowedProtocol) => allowedProtocol.replace(/:$/, '').toLowerCase())
        .includes(protocol)
}
