function isPlainRecord(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
export function installRequestParams(payload, options = {}) {
    let last = {};
    const handle = { current: () => last };
    if (typeof payload.ajax === 'function') {
        const originalAjax = payload.ajax;
        payload.ajax = (data, ...rest) => {
            if (options.filters) {
                data.filters = options.filters();
            }
            last = data;
            return originalAjax(data, ...rest);
        };
        return handle;
    }
    if (!isPlainRecord(payload.ajax)) {
        return handle;
    }
    const userData = payload.ajax.data;
    payload.ajax.data = (data, settings) => {
        if (isPlainRecord(userData)) {
            Object.assign(data, userData);
        }
        if (options.filters) {
            data.filters = options.filters();
        }
        const sent = typeof userData === 'function' ? callUserData(data, userData, settings) : data;
        const returned = (typeof sent === 'string' ? parseJsonRecord(sent) : sent) ?? data;
        const params = options.transport ? { ...data, ...returned } : returned;
        if (options.filters && undefined === params.filters) {
            params.filters = data.filters;
        }
        last = params;
        if (options.transport) {
            return options.transport(params);
        }
        return sent;
    };
    return handle;
}
function callUserData(data, userData, settings) {
    const returned = userData(data, settings);
    return typeof returned === 'string' || isPlainRecord(returned) ? returned : data;
}
function parseJsonRecord(sent) {
    try {
        const parsed = JSON.parse(sent);
        return isPlainRecord(parsed) ? parsed : null;
    }
    catch {
        return null;
    }
}
//# sourceMappingURL=requestParams.js.map