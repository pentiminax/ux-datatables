import type { BulkActionsConfig } from '../bulk/BulkActionBar.js'
import type { ColumnConfig } from '../functions/apiPlatformAdapter.js'
import type { EmptyStateConfig } from '../functions/emptyState.js'
import type { FilterBarLabels, FilterDefinition } from '../functions/filters.js'
import type { HighlightConfig } from '../functions/highlightUpdates.js'
import type { MercureConfig } from '../functions/mercureSubscription.js'
import type { RowActionLabels } from '../functions/rowActionFeedback.js'
import type { RowActionName } from '../functions/rowActionUrl.js'
import type { UrlStateConfig } from '../functions/urlState.js'
import type { StyleFramework } from './styleFramework.js'

export type DataTableViewColumn = ColumnConfig & Record<string, unknown>

/**
 * The payload `render_datatable()` hands the Stimulus controller, as `PreparedDataTable::getView()`
 * builds it. DataTables.net options and extensions pass through untouched, so they stay unknown.
 */
export interface DataTableView {
    columns: DataTableViewColumn[]
    /** The registered table token, `null` for a table without a DataTable class. */
    dataTable: string | null
    editModal: { adapter: string | null }
    mutationsEnabled: boolean

    data?: Record<string, unknown>[]
    serverSide?: boolean
    layout?: Record<string, unknown>
    language?: Record<string, unknown>

    apiPlatform?: boolean
    apiPlatformServerSide?: boolean
    pollInterval?: number
    urlState?: UrlStateConfig
    styleFramework?: StyleFramework
    exportUrl?: string
    mercure?: MercureConfig
    highlight?: HighlightConfig
    rowId?: string
    bulkActions?: BulkActionsConfig
    rowActionUrls?: Partial<Record<RowActionName, string>>
    emptyState?: EmptyStateConfig
    recordUrl?: boolean
    actionLabels?: RowActionLabels & { confirm?: string; cancel?: string }
    summaryLabels?: Record<string, string>
    forwardedQuery?: Record<string, unknown>
    filters?: FilterDefinition[]
    showHeaderResetButton?: boolean
    filterLabels?: FilterBarLabels
    locale?: string
    csrfToken?: string

    [option: string]: unknown
}
