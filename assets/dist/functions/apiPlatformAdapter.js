function toPositiveLength(length) {
    return typeof length === 'number' && Number.isFinite(length) && length > 0
        ? Math.floor(length)
        : 10;
}

function toNonNegativeInt(value) {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return 0;
    }

    return Math.max(0, Math.floor(value));
}

function toDraw(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value));
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    }

    return 0;
}

function toCount(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value));
    }

    return 0;
}

function isRecord(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function convertDataTableToApiPlatform(params, columns) {
    const result = {};
    const length = toPositiveLength(params.length);
    const start = toNonNegativeInt(params.start);

    result.page = String(Math.floor(start / length) + 1);
    result.itemsPerPage = String(length);

    for (const order of params.order ?? []) {
        const fieldName = columns[order.column].name;
        result[`order[${fieldName}]`] = order.dir === 'desc' ? 'desc' : 'asc';
    }

    for (const column of params.columns ?? []) {
        const searchValue = column.search?.value;

        if (typeof searchValue !== 'string' || searchValue.trim() === '') {
            continue;
        }

        result[column.name] = searchValue;
    }

    return result;
}

export function convertApiPlatformToDataTable(json, draw) {
    const totalItems = toCount(json['hydra:totalItems'] ?? json.totalItems ?? 0);
    const data = Array.isArray(json['hydra:member'])
        ? json['hydra:member']
        : (Array.isArray(json.member) ? json.member : []);

    return {
        draw,
        recordsTotal: totalItems,
        recordsFiltered: totalItems,
        data,
    };
}

function normalizeAjaxConfig(payload) {
    if (typeof payload.ajax === 'string') {
        payload.ajax = {
            type: 'GET',
            url: payload.ajax,
        };
    }

    if (isRecord(payload.ajax)) {
        return payload.ajax;
    }

    payload.ajax = {type: 'GET'};

    return payload.ajax;
}

function resolveDataTableParams(params, originalData) {
    if (typeof originalData === 'function') {
        const transformed = originalData(params);
        if (isRecord(transformed)) {
            return transformed;
        }

        return params;
    }

    if (isRecord(originalData)) {
        return {
            ...params,
            ...originalData,
        };
    }

    return params;
}

function resolveRawResponse(rawData, type, originalDataFilter) {
    if (typeof originalDataFilter === 'function') {
        return originalDataFilter(rawData, type ?? '');
    }

    return rawData;
}

function parseResponsePayload(rawData) {
    if (isRecord(rawData)) {
        return rawData;
    }

    if (typeof rawData !== 'string') {
        return null;
    }

    try {
        const parsed = JSON.parse(rawData);

        return isRecord(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

export function configureApiPlatformAjax(payload) {
    const columns = Array.isArray(payload.columns) ? payload.columns : [];
    const ajaxConfig = normalizeAjaxConfig(payload);
    const originalData = ajaxConfig.data;
    const originalDataFilter = ajaxConfig.dataFilter;
    let draw = 0;

    ajaxConfig.data = (params) => {
        const resolvedParams = resolveDataTableParams(params, originalData);
        draw = toDraw(resolvedParams.draw);

        return convertDataTableToApiPlatform(resolvedParams, columns);
    };

    ajaxConfig.dataFilter = (rawData, type) => {
        const filteredRawData = resolveRawResponse(rawData, type, originalDataFilter);
        const parsedPayload = parseResponsePayload(filteredRawData);

        if (null === parsedPayload) {
            return typeof filteredRawData === 'string' ? filteredRawData : rawData;
        }

        return JSON.stringify(convertApiPlatformToDataTable(parsedPayload, draw));
    };
}
