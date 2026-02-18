export interface DataTableServerSideOrder {
    column: number;
    dir: 'asc' | 'desc' | string;
}

export interface DataTableServerSideSearch {
    value?: string | null;
}

export interface DataTableServerSideColumn {
    name: string;
    search?: DataTableServerSideSearch;
}

export interface DataTableServerSideParams {
    draw?: number | string;
    start?: number;
    length?: number;
    order?: DataTableServerSideOrder[];
    columns?: DataTableServerSideColumn[];
}

export interface ColumnConfig {
    name: string;
}

export interface HydraCollectionResponse {
    'hydra:member'?: unknown[];
    member?: unknown[];
    'hydra:totalItems'?: number;
    totalItems?: number;
}

export interface DataTableServerSideResponse {
    draw: number;
    recordsTotal: number;
    recordsFiltered: number;
    data: unknown[];
}

function toPositiveLength(length: number | undefined): number {
    return typeof length === 'number' && Number.isFinite(length) && length > 0
        ? Math.floor(length)
        : 10;
}

function toNonNegativeInt(value: number | undefined): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return 0;
    }

    return Math.max(0, Math.floor(value));
}

function toDraw(value: number | string | undefined): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value));
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    }

    return 0;
}

function toCount(value: unknown): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value));
    }

    return 0;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function convertDataTableToApiPlatform(
    params: DataTableServerSideParams,
    columns: ColumnConfig[]
): Record<string, string> {
    const result: Record<string, string> = {};
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

export function convertApiPlatformToDataTable(
    json: HydraCollectionResponse,
    draw: number
): DataTableServerSideResponse {
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

function normalizeAjaxConfig(payload: Record<string, unknown>): Record<string, unknown> {
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

    return payload.ajax as Record<string, unknown>;
}

function resolveDataTableParams(
    params: DataTableServerSideParams,
    originalData: unknown
): DataTableServerSideParams {
    if (typeof originalData === 'function') {
        const transformed = (originalData as (value: DataTableServerSideParams) => unknown)(params);
        if (isRecord(transformed)) {
            return transformed as DataTableServerSideParams;
        }

        return params;
    }

    if (isRecord(originalData)) {
        return {
            ...params,
            ...originalData,
        } as DataTableServerSideParams;
    }

    return params;
}

function resolveRawResponse(
    rawData: string,
    type: string | undefined,
    originalDataFilter: unknown
): unknown {
    if (typeof originalDataFilter === 'function') {
        return (originalDataFilter as (data: string, type: string) => unknown)(rawData, type ?? '');
    }

    return rawData;
}

function parseResponsePayload(rawData: unknown): HydraCollectionResponse | null {
    if (isRecord(rawData)) {
        return rawData as HydraCollectionResponse;
    }

    if (typeof rawData !== 'string') {
        return null;
    }

    try {
        const parsed = JSON.parse(rawData);

        return isRecord(parsed) ? parsed as HydraCollectionResponse : null;
    } catch {
        return null;
    }
}

export function configureApiPlatformAjax(payload: Record<string, unknown>): void {
    const columns: ColumnConfig[] = Array.isArray(payload.columns) ? payload.columns as ColumnConfig[] : [];
    const ajaxConfig = normalizeAjaxConfig(payload);
    const originalData = ajaxConfig.data;
    const originalDataFilter = ajaxConfig.dataFilter;
    let draw = 0;

    ajaxConfig.data = (params: DataTableServerSideParams): Record<string, string> => {
        const resolvedParams = resolveDataTableParams(params, originalData);
        draw = toDraw(resolvedParams.draw);

        return convertDataTableToApiPlatform(resolvedParams, columns);
    };

    ajaxConfig.dataFilter = (rawData: string, type: string): string => {
        const filteredRawData = resolveRawResponse(rawData, type, originalDataFilter);
        const parsedPayload = parseResponsePayload(filteredRawData);

        if (null === parsedPayload) {
            return typeof filteredRawData === 'string' ? filteredRawData : rawData;
        }

        return JSON.stringify(convertApiPlatformToDataTable(parsedPayload, draw));
    };
}
