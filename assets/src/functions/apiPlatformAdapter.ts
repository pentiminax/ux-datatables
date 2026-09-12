interface DataTableServerSideOrder {
    column: number
    dir: 'asc' | 'desc' | string
}

interface DataTableServerSideSearch {
    value?: string | null
}

interface DataTableServerSideColumn {
    data?: string | null
    name?: string | null
    search?: DataTableServerSideSearch
}

interface DataTableServerSideParams {
    draw?: number | string
    start?: number
    length?: number
    order?: DataTableServerSideOrder[]
    columns?: DataTableServerSideColumn[]
    search?: DataTableServerSideSearch
    filters?: unknown
}

export interface ColumnConfig {
    customOptions?: Record<string, unknown>
    data?: string | null
    defaultContent?: string
    field?: string | null
    name: string
}

/**
 * Template columns are rendered server-side under their own `data ?? name` key, so the `field`
 * override must stay reserved for nested relation columns.
 */
export function resolveColumnDataKey(column: ColumnConfig): string | null | undefined {
    if (column.customOptions?.templatePath) {
        return column.data ?? column.name
    }

    return column.field ?? column.data
}

interface HydraCollectionResponse {
    'hydra:member'?: unknown[]
    member?: unknown[]
    'hydra:totalItems'?: number
    totalItems?: number
}

interface DataTableServerSideResponse {
    draw: number
    recordsTotal: number
    recordsFiltered: number
    data: unknown[]
}

interface ApiPlatformTemplateRenderingConfig {
    table: string
    url: string
}

type DataTableAjaxCallback = (response: DataTableServerSideResponse) => void

function toPositiveLength(length: number | undefined): number {
    return typeof length === 'number' && Number.isFinite(length) && length > 0
        ? Math.floor(length)
        : 10
}

function toNonNegativeInt(value: number | undefined): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return 0
    }

    return Math.max(0, Math.floor(value))
}

function toCount(value: unknown): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.max(0, Math.floor(value))
    }

    return 0
}

/**
 * Mirrors `HighlightConfig::ROW_ID_KEY`; duplicated rather than imported so the highlight module
 * stays lazily loaded.
 */
const ROW_ID_KEY = 'DT_RowId'

const DEFAULT_ROW_ID_FIELD = 'id'

function resolveRowIdField(highlight: unknown): string | null {
    if (!isRecord(highlight)) {
        return null
    }

    const idField = highlight.idField

    return 'string' === typeof idField && '' !== idField ? idField : DEFAULT_ROW_ID_FIELD
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export class ApiPlatformAdapter {
    private readonly columns: ReadonlyArray<ColumnConfig>

    /**
     * Hydra members never go through the PHP row mapper, so the row id the update highlight and the
     * DataTables `rowId` option read is added here instead of by `RowIdStage`.
     */
    private rowIdField: string | null = null

    constructor(columns: ColumnConfig[]) {
        this.columns = columns
    }

    buildRequestParams(params: DataTableServerSideParams): Record<string, string> {
        const result: Record<string, string> = {}
        const length = toPositiveLength(params.length)
        const start = toNonNegativeInt(params.start)

        result.page = String(Math.floor(start / length) + 1)
        result.itemsPerPage = String(length)

        const globalSearchValue = params.search?.value
        if (typeof globalSearchValue === 'string' && globalSearchValue.trim() !== '') {
            result.q = globalSearchValue.trim()
        }

        for (const order of params.order ?? []) {
            const columnConfig = this.resolveColumnConfig(
                params.columns?.[order.column],
                order.column
            )

            if (undefined === columnConfig) {
                continue
            }

            const fieldName = columnConfig.field ?? columnConfig.data ?? columnConfig.name

            if (null === fieldName) {
                continue
            }

            result[`order[${fieldName}]`] = order.dir === 'desc' ? 'desc' : 'asc'
        }

        for (const [index, column] of (params.columns ?? []).entries()) {
            const searchValue = column.search?.value

            if (typeof searchValue !== 'string' || searchValue.trim() === '') {
                continue
            }

            const columnConfig = this.resolveColumnConfig(column, index)

            if (undefined === columnConfig) {
                continue
            }

            const fieldName = columnConfig.field ?? columnConfig.data ?? columnConfig.name

            if (null === fieldName) {
                continue
            }

            result[fieldName] = searchValue
        }

        this.appendFilters(result, params.filters)

        return result
    }

    buildResponse(json: HydraCollectionResponse, draw: number): DataTableServerSideResponse {
        const totalItems = toCount(json['hydra:totalItems'] ?? json.totalItems ?? 0)
        const data = Array.isArray(json['hydra:member'])
            ? json['hydra:member']
            : Array.isArray(json.member)
              ? json.member
              : []

        return {
            draw,
            recordsTotal: totalItems,
            recordsFiltered: totalItems,
            data: this.withRowIds(data),
        }
    }

    configure(payload: Record<string, unknown>): void {
        const ajaxConfig = payload.ajax as Record<string, unknown>
        this.rowIdField = resolveRowIdField(payload.highlight)
        const originalData = ajaxConfig.data
        const originalDataFilter = ajaxConfig.dataFilter
        const templateRendering = this.resolveTemplateRenderingConfig(
            payload.apiPlatformTemplateRendering
        )

        payload.serverSide = true
        payload.columns = this.withDefaultColumnContent(payload.columns)

        if (null !== templateRendering) {
            payload.ajax = (
                params: DataTableServerSideParams,
                callback: DataTableAjaxCallback
            ): void => {
                void this.fetchDataTableResponse(
                    ajaxConfig,
                    params,
                    originalData,
                    originalDataFilter,
                    templateRendering
                ).then(callback)
            }

            return
        }

        let draw = 0

        const buildData = (params: DataTableServerSideParams): Record<string, string> => {
            const resolvedParams = this.resolveDataTableParams(params, originalData)
            draw = this.toDraw(resolvedParams.draw)

            return this.buildRequestParams(resolvedParams)
        }
        buildData.consumesFilters = true

        ajaxConfig.data = buildData

        ajaxConfig.dataFilter = (rawData: string, type: string): string => {
            const filteredRawData = this.resolveRawResponse(rawData, type, originalDataFilter)
            const parsedPayload: HydraCollectionResponse | null =
                this.parseResponsePayload(filteredRawData)

            if (null === parsedPayload) {
                return typeof filteredRawData === 'string' ? filteredRawData : rawData
            }

            const response = this.buildResponse(parsedPayload, draw)

            return JSON.stringify(response)
        }
    }

    async fetchDataTableResponse(
        ajaxConfig: Record<string, unknown>,
        params: DataTableServerSideParams,
        originalData: unknown,
        originalDataFilter: unknown,
        templateRendering: ApiPlatformTemplateRenderingConfig
    ): Promise<DataTableServerSideResponse> {
        const resolvedParams = this.resolveDataTableParams(params, originalData)
        const draw = this.toDraw(resolvedParams.draw)
        const queryParams = this.buildRequestParams(resolvedParams)
        const rawData = await this.fetchApiPlatformData(ajaxConfig, queryParams)
        const filteredRawData = this.resolveRawResponse(rawData, 'json', originalDataFilter)
        const parsedPayload = this.parseResponsePayload(filteredRawData)

        if (null === parsedPayload) {
            return {
                draw,
                recordsTotal: 0,
                recordsFiltered: 0,
                data: [],
            }
        }

        return this.renderTemplateRows(this.buildResponse(parsedPayload, draw), templateRendering)
    }

    withDefaultColumnContent(columns: unknown): unknown {
        if (!Array.isArray(columns)) {
            return columns
        }

        return columns.map((column: unknown): unknown => {
            if (!isRecord(column) || typeof column.defaultContent === 'string') {
                return column
            }

            return {
                ...column,
                defaultContent: '',
            }
        })
    }

    async fetchApiPlatformData(
        ajaxConfig: Record<string, unknown>,
        params: Record<string, string>
    ): Promise<string> {
        const url = typeof ajaxConfig.url === 'string' ? ajaxConfig.url : ''
        const methodValue = ajaxConfig.type ?? ajaxConfig.method
        const method = typeof methodValue === 'string' ? methodValue.toUpperCase() : 'GET'
        const query = new URLSearchParams(params)
        const headers = this.resolveHeaders(ajaxConfig.headers)

        const requestInit: RequestInit = {
            credentials: 'same-origin',
            method,
        }

        if ('GET' === method) {
            return fetch(this.appendQueryString(url, query), requestInit).then((response) =>
                response.text()
            )
        }

        requestInit.headers = headers
        requestInit.body = query

        return fetch(url, requestInit).then((response) => response.text())
    }

    async renderTemplateRows(
        response: DataTableServerSideResponse,
        templateRendering: ApiPlatformTemplateRenderingConfig
    ): Promise<DataTableServerSideResponse> {
        if (response.data.length === 0) {
            return response
        }

        let renderedResponse: Response

        try {
            renderedResponse = await fetch(templateRendering.url, {
                body: JSON.stringify({
                    table: templateRendering.table,
                    rows: response.data,
                }),
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                method: 'POST',
            })
        } catch (error) {
            console.warn('Template rendering request failed. Rows are displayed unrendered.', error)

            return response
        }

        if (!renderedResponse.ok) {
            console.warn(
                `Template rendering failed (${renderedResponse.status}). Rows displayed unrendered.`
            )

            return response
        }

        let renderedPayload: unknown

        try {
            renderedPayload = await renderedResponse.json()
        } catch (error) {
            console.warn(
                'Template rendering returned an unreadable body. Rows are displayed unrendered.',
                error
            )

            return response
        }

        const data =
            isRecord(renderedPayload) && Array.isArray(renderedPayload.data)
                ? renderedPayload.data
                : response.data

        return {
            ...response,
            data,
        }
    }

    resolveTemplateRenderingConfig(value: unknown): ApiPlatformTemplateRenderingConfig | null {
        if (!isRecord(value)) {
            return null
        }

        return typeof value.url === 'string' &&
            value.url.trim() !== '' &&
            typeof value.table === 'string' &&
            value.table.trim() !== ''
            ? { url: value.url, table: value.table }
            : null
    }

    /**
     * Mirrors `DefaultDataTableQueryIntentFactory`: request columns are matched onto the configured
     * ones by name, never by display index, because the client may prepend columns the server never
     * configured (the Select extension unshifts a checkbox column in `checkbox` mode).
     */
    private resolveColumnConfig(
        requestColumn: DataTableServerSideColumn | undefined,
        index: number
    ): ColumnConfig | undefined {
        const key = requestColumn?.name || requestColumn?.data

        if ('string' === typeof key && '' !== key) {
            return (
                this.columns.find((column) => column.name === key) ??
                this.columns.find((column) => column.data === key) ??
                this.columns.find((column) => column.field === key)
            )
        }

        return this.columns[index]
    }

    /**
     * Filter bar values are flattened into API Platform's query shape: scalars become `name=value`,
     * multi-selects `name[0]=value`, and date ranges `name[after]` / `name[before]`. Left as the
     * nested `filters` object they were silently dropped by API Platform.
     */
    private appendFilters(result: Record<string, string>, filters: unknown): void {
        if (!isRecord(filters)) {
            return
        }

        for (const [name, value] of Object.entries(filters)) {
            if ('string' === typeof value) {
                if ('' !== value.trim()) {
                    this.setFilterParam(result, name, value)
                }

                continue
            }

            if (Array.isArray(value)) {
                let index = 0
                for (const entry of value) {
                    if ('string' !== typeof entry || '' === entry) {
                        continue
                    }

                    this.setFilterParam(result, `${name}[${index}]`, entry)
                    index++
                }

                continue
            }

            if (!isRecord(value)) {
                continue
            }

            const from = value.from
            const to = value.to

            if ('string' === typeof from && '' !== from.trim()) {
                this.setFilterParam(result, `${name}[after]`, from)
            }

            if ('string' === typeof to && '' !== to.trim()) {
                this.setFilterParam(result, `${name}[before]`, to)
            }
        }
    }

    /**
     * Filter names are free-form, so one called `page` or `itemsPerPage` would otherwise silently
     * replace the pagination the table just requested. Protocol parameters win.
     */
    private setFilterParam(result: Record<string, string>, key: string, value: string): void {
        if (key in result) {
            return
        }

        result[key] = value
    }

    private withRowIds(rows: unknown[]): unknown[] {
        const field = this.rowIdField

        if (null === field) {
            return rows
        }

        return rows.map((row: unknown): unknown => {
            if (!isRecord(row) || row[ROW_ID_KEY] !== undefined) {
                return row
            }

            const id = row[field]

            if ('number' !== typeof id && ('string' !== typeof id || '' === id)) {
                return row
            }

            return {
                ...row,
                [ROW_ID_KEY]: String(id),
            }
        })
    }

    private appendQueryString(url: string, params: URLSearchParams): string {
        const query = params.toString()

        if ('' === query) {
            return url
        }

        return url.includes('?') ? `${url}&${query}` : `${url}?${query}`
    }

    private resolveHeaders(headers: unknown): HeadersInit | undefined {
        return isRecord(headers) ? (headers as HeadersInit) : undefined
    }

    parseResponsePayload(rawData: unknown): HydraCollectionResponse | null {
        if (isRecord(rawData)) {
            return rawData as HydraCollectionResponse
        }

        if (typeof rawData !== 'string') {
            return null
        }

        try {
            const parsed = JSON.parse(rawData)

            return isRecord(parsed) ? (parsed as HydraCollectionResponse) : null
        } catch {
            return null
        }
    }

    resolveRawResponse(
        rawData: string,
        type: string | undefined,
        originalDataFilter: unknown
    ): unknown {
        if (typeof originalDataFilter === 'function') {
            return (originalDataFilter as (data: string, type: string) => unknown)(
                rawData,
                type ?? ''
            )
        }

        return rawData
    }

    resolveDataTableParams(
        params: DataTableServerSideParams,
        originalData: unknown
    ): DataTableServerSideParams {
        if (typeof originalData === 'function') {
            const transformed = (originalData as (value: DataTableServerSideParams) => unknown)(
                params
            )
            if (isRecord(transformed)) {
                return this.withFilters(transformed as DataTableServerSideParams, params.filters)
            }

            return params
        }

        if (isRecord(originalData)) {
            return {
                ...params,
                ...originalData,
            } as DataTableServerSideParams
        }

        return params
    }

    /**
     * A user `ajax.data` callback may return a replacement object rather than the request params it
     * was handed. The filter bar merges its values into the original object, so they are carried
     * over unless the callback set its own.
     */
    private withFilters(
        params: DataTableServerSideParams,
        filters: unknown
    ): DataTableServerSideParams {
        if (undefined === filters || undefined !== params.filters) {
            return params
        }

        return {
            ...params,
            filters,
        }
    }

    toDraw(value: number | string | undefined): number {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return Math.max(0, Math.floor(value))
        }

        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number.parseInt(value, 10)
            return Number.isFinite(parsed) ? Math.max(0, parsed) : 0
        }

        return 0
    }
}
