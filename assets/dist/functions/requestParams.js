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
        if (options.filters) {
            data.filters = options.filters();
        }
        const sent = applyUserData(data, userData, settings);
        const params = typeof sent === 'string' ? data : sent;
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
function applyUserData(data, userData, settings) {
    if (typeof userData === 'function') {
        const returned = userData(data, settings);
        if (typeof returned === 'string' || isPlainRecord(returned)) {
            return returned;
        }
        return data;
    }
    if (isPlainRecord(userData)) {
        Object.assign(data, userData);
    }
    return data;
}
//# sourceMappingURL=requestParams.js.map