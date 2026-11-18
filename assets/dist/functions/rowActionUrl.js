export function rowActionUrl(payload, name) {
    const url = payload.rowActionUrls?.[name];
    if (typeof url !== 'string' || url.length === 0) {
        throw new Error(`The payload has no "${name}" row action URL.`);
    }
    return url;
}
//# sourceMappingURL=rowActionUrl.js.map