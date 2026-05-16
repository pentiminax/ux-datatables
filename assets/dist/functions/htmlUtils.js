export function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
export function parseBooleanValue(value, defaultValue = false) {
    if (null === value || undefined === value || '' === value) {
        return defaultValue;
    }
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        return value !== 0;
    }
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        return ['1', 'true', 'yes', 'y', 'on'].includes(normalized);
    }
    return false;
}
export function isUnsafeUrl(url) {
    const normalized = url.replace(/[\u0000-\u0020]+/g, '').toLowerCase();
    return (normalized.startsWith('javascript:') ||
        normalized.startsWith('data:') ||
        normalized.startsWith('vbscript:') ||
        normalized.startsWith('file:'));
}
export function getUrlProtocol(url) {
    const normalized = url.trimStart();
    const matches = normalized.match(/^([a-z][a-z0-9+.-]*):/i);
    return matches?.[1].toLowerCase() ?? null;
}
export function withDefaultProtocol(url, defaultProtocol) {
    if (!defaultProtocol || getUrlProtocol(url) || url.startsWith('/') || url.startsWith('#')) {
        return url;
    }
    return `${defaultProtocol.replace(/:$/, '')}://${url}`;
}
export function isAllowedUrlProtocol(url, allowedProtocols) {
    if (!allowedProtocols) {
        return true;
    }
    const protocol = getUrlProtocol(url);
    if (!protocol) {
        return false;
    }
    return allowedProtocols
        .map((allowedProtocol) => allowedProtocol.replace(/:$/, '').toLowerCase())
        .includes(protocol);
}
//# sourceMappingURL=htmlUtils.js.map