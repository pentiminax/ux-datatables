import { buttonActions } from './buttonActionRegistry.js';
export const SERVER_EXPORT_ACTION = 'ux:export';
export function flattenFormValues(data, prefix = '', acc = []) {
    if (data === null || data === undefined) {
        if (prefix !== '') {
            acc.push({ name: prefix, value: '' });
        }
        return acc;
    }
    if (Array.isArray(data)) {
        for (const [index, item] of data.entries()) {
            flattenFormValues(item, prefix === '' ? String(index) : `${prefix}[${index}]`, acc);
        }
        return acc;
    }
    if (typeof data === 'object') {
        for (const [key, value] of Object.entries(data)) {
            flattenFormValues(value, prefix === '' ? key : `${prefix}[${key}]`, acc);
        }
        return acc;
    }
    if (prefix !== '') {
        acc.push({ name: prefix, value: String(data) });
    }
    return acc;
}
export function applyServerExportUrls(payload, protocolParams) {
    const url = payload.exportUrl;
    if (typeof url !== 'string' || url === '') {
        return;
    }
    const layout = payload.layout;
    if (typeof layout === 'object' && layout !== null && !Array.isArray(layout)) {
        const record = layout;
        for (const position of Object.keys(record)) {
            stampExportUrl(record[position], url, protocolParams);
        }
    }
    delete payload.exportUrl;
}
function stampExportUrl(value, url, protocolParams) {
    if (Array.isArray(value)) {
        for (const item of value) {
            stampExportUrl(item, url, protocolParams);
        }
        return;
    }
    if (typeof value !== 'object' || value === null) {
        return;
    }
    const record = value;
    if (record.action === SERVER_EXPORT_ACTION && typeof record.url !== 'string') {
        record.url = url;
        if (protocolParams) {
            record.protocolParams = protocolParams;
        }
    }
    for (const key of ['buttons', 'postfixButtons', 'prefixButtons']) {
        if (Array.isArray(record[key])) {
            stampExportUrl(record[key], url, protocolParams);
        }
    }
}
export function resolveExportParams(dt, config) {
    if (typeof config.protocolParams === 'function') {
        return config.protocolParams();
    }
    const ajax = dt.ajax;
    if (typeof ajax?.params === 'function') {
        const wire = ajax.params();
        return wire !== null && typeof wire === 'object' && !Array.isArray(wire) ? wire : {};
    }
    return {};
}
export function runServerExport(_e, dt, _node, config) {
    const exportConfig = config;
    const protocol = resolveExportParams(dt, exportConfig);
    if (typeof exportConfig.protocolParams !== 'function' &&
        typeof dt.ajax?.params !== 'function') {
        console.error('Server-side export requires an Ajax DataTable.');
        return;
    }
    const url = typeof exportConfig.url === 'string' ? exportConfig.url : '';
    if (url === '') {
        console.error('Server-side export is missing an export URL.');
        return;
    }
    const params = {
        ...protocol,
        start: 0,
        length: 0,
        exportKey: typeof exportConfig.exportKey === 'string' ? exportConfig.exportKey : 'csv',
        format: typeof exportConfig.format === 'string' ? exportConfig.format : 'csv',
    };
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';
    for (const field of flattenFormValues(params)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = field.name;
        input.value = field.value;
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
    form.remove();
}
buttonActions.register(SERVER_EXPORT_ACTION, runServerExport);
//# sourceMappingURL=serverExport.js.map