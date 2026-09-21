export function resolveColumnDataKey(column) {
    if (column.customOptions?.templatePath) {
        return column.data ?? column.name;
    }
    return column.field ?? column.data;
}
export function isApiPlatformAdapterEnabled(payload) {
    return true === payload?.apiPlatform && true !== payload?.apiPlatformServerSide;
}
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
function toCount(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value));
    }
    return 0;
}
const ROW_ID_KEY = 'DT_RowId';
const DEFAULT_ROW_ID_FIELD = 'id';
function resolveRowIdField(highlight) {
    if (!isRecord(highlight)) {
        return null;
    }
    const idField = highlight.idField;
    return 'string' === typeof idField && '' !== idField ? idField : DEFAULT_ROW_ID_FIELD;
}
function isRecord(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
export class ApiPlatformAdapter {
    constructor(columns) {
        this.rowIdField = null;
        this.columns = columns;
    }
    buildRequestParams(params) {
        const result = {};
        const length = toPositiveLength(params.length);
        const start = toNonNegativeInt(params.start);
        result.page = String(Math.floor(start / length) + 1);
        result.itemsPerPage = String(length);
        const globalSearchValue = params.search?.value;
        if (typeof globalSearchValue === 'string' && globalSearchValue.trim() !== '') {
            result.q = globalSearchValue.trim();
        }
        for (const order of params.order ?? []) {
            const columnConfig = this.resolveColumnConfig(params.columns?.[order.column], order.column);
            if (undefined === columnConfig) {
                continue;
            }
            const fieldName = columnConfig.field ?? columnConfig.data ?? columnConfig.name;
            if (null === fieldName) {
                continue;
            }
            result[`order[${fieldName}]`] = order.dir === 'desc' ? 'desc' : 'asc';
        }
        for (const [index, column] of (params.columns ?? []).entries()) {
            const searchValue = column.search?.value;
            if (typeof searchValue !== 'string' || searchValue.trim() === '') {
                continue;
            }
            const columnConfig = this.resolveColumnConfig(column, index);
            if (undefined === columnConfig) {
                continue;
            }
            const fieldName = columnConfig.field ?? columnConfig.data ?? columnConfig.name;
            if (null === fieldName) {
                continue;
            }
            result[fieldName] = searchValue;
        }
        this.appendFilters(result, params.filters);
        return result;
    }
    buildResponse(json, draw) {
        const totalItems = toCount(json['hydra:totalItems'] ?? json.totalItems ?? 0);
        const data = Array.isArray(json['hydra:member'])
            ? json['hydra:member']
            : Array.isArray(json.member)
                ? json.member
                : [];
        return {
            draw,
            recordsTotal: totalItems,
            recordsFiltered: totalItems,
            data: this.withRowIds(data),
        };
    }
    configure(payload) {
        const ajaxConfig = payload.ajax;
        this.rowIdField = resolveRowIdField(payload.highlight);
        const originalData = ajaxConfig.data;
        const originalDataFilter = ajaxConfig.dataFilter;
        payload.serverSide = true;
        payload.columns = this.withDefaultColumnContent(payload.columns);
        let draw = 0;
        const buildData = (params) => {
            const resolvedParams = this.resolveDataTableParams(params, originalData);
            draw = this.toDraw(resolvedParams.draw);
            return this.buildRequestParams(resolvedParams);
        };
        buildData.consumesFilters = true;
        ajaxConfig.data = buildData;
        ajaxConfig.dataFilter = (rawData, type) => {
            const filteredRawData = this.resolveRawResponse(rawData, type, originalDataFilter);
            const parsedPayload = this.parseResponsePayload(filteredRawData);
            if (null === parsedPayload) {
                return typeof filteredRawData === 'string' ? filteredRawData : rawData;
            }
            const response = this.buildResponse(parsedPayload, draw);
            return JSON.stringify(response);
        };
    }
    withDefaultColumnContent(columns) {
        if (!Array.isArray(columns)) {
            return columns;
        }
        return columns.map((column) => {
            if (!isRecord(column) || typeof column.defaultContent === 'string') {
                return column;
            }
            return {
                ...column,
                defaultContent: '',
            };
        });
    }
    resolveColumnConfig(requestColumn, index) {
        const key = requestColumn?.name || requestColumn?.data;
        if ('string' === typeof key && '' !== key) {
            return (this.columns.find((column) => column.name === key) ??
                this.columns.find((column) => column.data === key) ??
                this.columns.find((column) => column.field === key));
        }
        return this.columns[index];
    }
    appendFilters(result, filters) {
        if (!isRecord(filters)) {
            return;
        }
        for (const [name, value] of Object.entries(filters)) {
            if ('string' === typeof value) {
                if ('' !== value.trim()) {
                    this.setFilterParam(result, name, value);
                }
                continue;
            }
            if (Array.isArray(value)) {
                let index = 0;
                for (const entry of value) {
                    if ('string' !== typeof entry || '' === entry) {
                        continue;
                    }
                    this.setFilterParam(result, `${name}[${index}]`, entry);
                    index++;
                }
                continue;
            }
            if (!isRecord(value)) {
                continue;
            }
            const from = value.from;
            const to = value.to;
            if ('string' === typeof from && '' !== from.trim()) {
                this.setFilterParam(result, `${name}[after]`, from);
            }
            if ('string' === typeof to && '' !== to.trim()) {
                this.setFilterParam(result, `${name}[before]`, to);
            }
        }
    }
    setFilterParam(result, key, value) {
        if (key in result) {
            return;
        }
        result[key] = value;
    }
    withRowIds(rows) {
        const field = this.rowIdField;
        if (null === field) {
            return rows;
        }
        return rows.map((row) => {
            if (!isRecord(row) || row[ROW_ID_KEY] !== undefined) {
                return row;
            }
            const id = row[field];
            if ('number' !== typeof id && ('string' !== typeof id || '' === id)) {
                return row;
            }
            return {
                ...row,
                [ROW_ID_KEY]: String(id),
            };
        });
    }
    parseResponsePayload(rawData) {
        if (isRecord(rawData)) {
            return rawData;
        }
        if (typeof rawData !== 'string') {
            return null;
        }
        try {
            const parsed = JSON.parse(rawData);
            return isRecord(parsed) ? parsed : null;
        }
        catch {
            return null;
        }
    }
    resolveRawResponse(rawData, type, originalDataFilter) {
        if (typeof originalDataFilter === 'function') {
            return originalDataFilter(rawData, type ?? '');
        }
        return rawData;
    }
    resolveDataTableParams(params, originalData) {
        if (typeof originalData === 'function') {
            const transformed = originalData(params);
            if (isRecord(transformed)) {
                return this.withFilters(transformed, params.filters);
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
    withFilters(params, filters) {
        if (undefined === filters || undefined !== params.filters) {
            return params;
        }
        return {
            ...params,
            filters,
        };
    }
    toDraw(value) {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return Math.max(0, Math.floor(value));
        }
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number.parseInt(value, 10);
            return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        }
        return 0;
    }
}
//# sourceMappingURL=apiPlatformAdapter.js.map