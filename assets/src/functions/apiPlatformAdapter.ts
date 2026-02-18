export interface DataTableServerSideOrder {
  column: number
  dir: 'asc' | 'desc' | string
}

export interface DataTableServerSideSearch {
  value?: string | null
}

export interface DataTableServerSideColumn {
  data?: string | null
  name?: string | null
  search?: DataTableServerSideSearch
}

export interface DataTableServerSideParams {
  draw?: number | string
  start?: number
  length?: number
  order?: DataTableServerSideOrder[]
  columns?: DataTableServerSideColumn[]
}

export interface ColumnConfig {
  data?: string | null
  field?: string | null
  name: string
}

export interface HydraCollectionResponse {
  'hydra:member'?: unknown[]
  member?: unknown[]
  'hydra:totalItems'?: number
  totalItems?: number
}

export interface DataTableServerSideResponse {
  draw: number
  recordsTotal: number
  recordsFiltered: number
  data: unknown[]
}

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

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export class ApiPlatformAdapter {
  private readonly columns: ReadonlyArray<ColumnConfig>

  constructor(columns: ColumnConfig[]) {
    this.columns = columns
  }

  buildRequestParams(params: DataTableServerSideParams): Record<string, string> {
    const result: Record<string, string> = {}
    const length = toPositiveLength(params.length)
    const start = toNonNegativeInt(params.start)

    result.page = String(Math.floor(start / length) + 1)
    result.itemsPerPage = String(length)

    for (const order of params.order ?? []) {
      const columnConfig = this.columns[order.column]
      const fieldName = columnConfig.name;

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

      const columnConfig = this.columns[index]
      const fieldName: string = columnConfig.name;

      if (null === fieldName) {
        continue
      }

      result[fieldName] = searchValue
    }

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
      data,
    }
  }

  configure(payload: Record<string, unknown>): void {
    const ajaxConfig = payload.ajax as Record<string, unknown>;
    const originalData = ajaxConfig.data
    const originalDataFilter = ajaxConfig.dataFilter

    payload.serverSide = true

    let draw = 0

    ajaxConfig.data = (params: DataTableServerSideParams): Record<string, string> => {
      const resolvedParams = this.resolveDataTableParams(params, originalData)
      draw = this.toDraw(resolvedParams.draw)

      return this.buildRequestParams(resolvedParams)
    }

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
      return (originalDataFilter as (data: string, type: string) => unknown)(rawData, type ?? '')
    }

    return rawData
  }

  resolveDataTableParams(
    params: DataTableServerSideParams,
    originalData: unknown
  ): DataTableServerSideParams {
    if (typeof originalData === 'function') {
      const transformed = (originalData as (value: DataTableServerSideParams) => unknown)(params)
      if (isRecord(transformed)) {
        return transformed as DataTableServerSideParams
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
