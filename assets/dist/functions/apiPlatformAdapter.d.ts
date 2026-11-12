interface DataTableServerSideOrder {
    column: number;
    dir: 'asc' | 'desc' | string;
}
interface DataTableServerSideSearch {
    value?: string | null;
}
interface DataTableServerSideColumn {
    data?: string | null;
    name?: string | null;
    search?: DataTableServerSideSearch;
}
interface DataTableServerSideParams {
    draw?: number | string;
    start?: number;
    length?: number;
    order?: DataTableServerSideOrder[];
    columns?: DataTableServerSideColumn[];
    search?: DataTableServerSideSearch;
    filters?: unknown;
}
export interface ColumnConfig {
    customOptions?: Record<string, unknown>;
    data?: string | null;
    defaultContent?: string;
    field?: string | null;
    name: string;
}
export declare function resolveColumnDataKey(column: ColumnConfig): string | null | undefined;
export declare function isApiPlatformAdapterEnabled(payload: Record<string, unknown>): boolean;
interface HydraCollectionResponse {
    'hydra:member'?: unknown[];
    member?: unknown[];
    'hydra:totalItems'?: number;
    totalItems?: number;
    'hydra:view'?: unknown;
    view?: unknown;
}
interface DataTableServerSideResponse {
    draw: number;
    recordsTotal: number;
    recordsFiltered: number;
    data: unknown[];
}
export declare class ApiPlatformAdapter {
    private readonly columns;
    private rowIdField;
    private draw;
    private lastStart;
    private lastLength;
    constructor(columns: ColumnConfig[]);
    buildRequestParams(params: DataTableServerSideParams): Record<string, string>;
    buildResponse(json: HydraCollectionResponse, draw: number): DataTableServerSideResponse;
    private resolveTotalItems;
    configure(payload: Record<string, unknown>): void;
    withDefaultColumnContent(columns: unknown): unknown;
    private resolveColumnConfig;
    private appendFilters;
    private setFilterParam;
    private withRowIds;
    parseResponsePayload(rawData: unknown): HydraCollectionResponse | null;
    resolveRawResponse(rawData: string, type: string | undefined, originalDataFilter: unknown): unknown;
    toRequestParams(params: DataTableServerSideParams): Record<string, string>;
    toDraw(value: number | string | undefined): number;
}
export {};
