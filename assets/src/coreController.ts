import { Controller } from '@hotwired/stimulus'
import { installBulkActionBar } from './bulk/BulkActionBar.js'
import { createActionColumnRenderer } from './columnRenderers/actionColumnRenderer.js'
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js'
import { createChoiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js'
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js'
import { createIconColumnRenderer } from './columnRenderers/iconColumnRenderer.js'
import { bindImagePlaceholder, imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js'
import { moneyColumnRenderer } from './columnRenderers/moneyColumnRenderer.js'
import { relativeDateColumnRenderer } from './columnRenderers/relativeDateColumnRenderer.js'
import { textColumnRenderer } from './columnRenderers/textColumnRenderer.js'
import { type ColumnRenderer, isFormatted } from './columnRenderers/types.js'
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js'
import { resolveColumnStyleAdapter } from './columnStyles/resolveColumnStyleAdapter.js'
import {
    ApiPlatformAdapter,
    isApiPlatformAdapterEnabled,
    resolveColumnDataKey,
} from './functions/apiPlatformAdapter.js'
import { applyCustomButtonActions } from './functions/applyCustomButtonActions.js'
import { normalizeDisabledColumnControls } from './functions/columnControl.js'
import {
    ensureSummaryFooter,
    hasSummaries,
    installColumnSummaries,
} from './functions/columnSummaries.js'
import { detectTheme } from './functions/detectTheme.js'
import { installFilterBar } from './functions/filters.js'
import { isHighlightEnabled, type UpdateHighlighter } from './functions/highlightUpdates.js'
import { isRecord } from './functions/htmlUtils.js'
import { isDataTableClone } from './functions/isDataTableClone.js'
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js'
import {
    hasLucideIcons,
    hasLucideIconsInActions,
    loadLucideIcons,
} from './functions/lucideIcons.js'
import type { MercureConfig } from './functions/mercureSubscription.js'
import { type ExtensionName, packageLoaders } from './functions/packageLoaders.js'
import { ensurePolling } from './functions/polling.js'
import { installRequestParams, type RequestParamsHandle } from './functions/requestParams.js'
import { restrictSearchBuilder } from './functions/restrictSearchBuilder.js'
import { applyTableLanguage, bindRowFeatures } from './functions/rowFeatures.js'
import { applyServerExportUrls } from './functions/serverExport.js'
import { shiftOrderForSelectColumn } from './functions/shiftOrderForSelectColumn.js'
import { applyThemeSearchPlaceholder } from './functions/themeSearchField.js'
import {
    applyUrlStateToPayload,
    isUrlStateEnabled,
    readUrlState,
    type UrlStateConfig,
    writeUrlState,
} from './functions/urlState.js'
import { installRowActions } from './rowActions/RowActions.js'
import type { DataTableView } from './types/dataTableView.js'
import {
    isStyleFramework,
    registeredStyleFramework,
    type StyleFramework,
} from './types/styleFramework.js'

/**
 * Maps DataTables payload property keys to the package loader of their extension.
 *
 * The payload key 'keys' maps to the 'keyTable' extension — this is an intentional
 * naming difference between the DataTables config API ('keys') and the extension
 * package name ('keyTable').
 */
const EXTENSION_MAP: Record<string, ExtensionName> = {
    select: 'select',
    responsive: 'responsive',
    columnControl: 'columnControl',
    fixedColumns: 'fixedColumns',
    fixedHeader: 'fixedHeader',
    colReorder: 'colReorder',
    keys: 'keyTable',
    rowGroup: 'rowGroup',
    scroller: 'scroller',
    searchBuilder: 'searchBuilder',
}

/**
 * Markers that only DataTables writes: its layout scaffolding, its built-in controls, the scrolling
 * containers, and the processed table itself. Style integrations rewrite the layout row's class -
 * Bootstrap 5 emits `row mt-2 justify-content-between` - but every one of them keeps the
 * `dt-layout-start`/`-end`/`-full` cells inside that row, so a marker is looked for anywhere in a
 * node's subtree rather than on the node itself.
 */
const GENERATED_MARKUP_SELECTOR = [
    '.dt-layout-row',
    '.dt-layout-cell',
    '.dt-layout-start',
    '.dt-layout-end',
    '.dt-layout-full',
    '.dt-length',
    '.dt-search',
    '.dt-info',
    '.dt-paging',
    '.dt-processing',
    '.dt-scroll',
    '.dt-buttons',
    'table.dataTable',
].join(',')

export default class extends Controller {
    declare readonly viewValue: DataTableView

    static readonly values = {
        view: Object,
    }

    private table: DataTableWithAjax | null = null
    private isDataTableInitialized = false
    private eventSource: EventSource | null = null
    private stopPolling: (() => void) | null = null
    private highlighter: UpdateHighlighter | null = null
    private framework: StyleFramework = 'dt'
    private popstateHandler: (() => void) | null = null

    /**
     * Turbo Drive caches a DOM snapshot before rendering the next page. A mounted DataTable makes
     * that snapshot dirty: it holds the generated wrapper and controls, and on Back/Forward a fresh
     * controller initializes on the restored clone because `DataTable.isDataTable()` reports false
     * for it — DataTables still tracks the original, now-detached node. Restoring the plain
     * `<table>` here keeps the snapshot clean. Never fires when Turbo is absent.
     *
     * `isDataTableInitialized` deliberately stays true. Turbo clones the DOM one event-loop tick
     * after dispatching this event, so the reparenting `destroy()` performs makes Stimulus
     * reconnect first, and re-initializing would put the wrapper straight back into the snapshot.
     * Turbo replaces the whole body a tick later, so the bare table is never rendered.
     */
    private readonly onTurboBeforeCache = (): void => {
        this.table?.destroy()
        this.table = null
    }

    async connect() {
        if (!(this.element instanceof HTMLTableElement)) {
            throw new Error('Invalid element')
        }

        if (isDataTableClone(this.element)) {
            return
        }

        // Registered before the initialized guard on purpose. DataTables' own DOM work reparents
        // the table, which Stimulus reports as disconnect + reconnect on a live table; the
        // reconnect early-returns below, so registering after the guard would drop this listener
        // for good on the first such cycle. A stable arrow property lets the DOM deduplicate
        // repeat registrations of the identical listener.
        document.addEventListener('turbo:before-cache', this.onTurboBeforeCache)

        if (this.isDataTableInitialized) {
            // `connect` stays a build-only event, so downstream code needs this one to bind again.
            if (this.table) {
                this.dispatchEvent('reconnect', { table: this.table })
                this.bindPopstate(isUrlStateEnabled(this.viewValue))
                this.initPolling(this.viewValue)
                // disconnect() closed the EventSource.
                await this.startMercure(this.viewValue)
            }

            return
        }

        const payload = this.viewValue

        this.dispatchEvent('pre-connect', {
            config: payload,
        })

        const framework = isStyleFramework(payload.styleFramework)
            ? payload.styleFramework
            : registeredStyleFramework()
        this.framework = framework

        const DataTable = await loadDataTableLibrary(framework)

        if (this.adoptLiveTable(DataTable)) {
            return
        }

        this.resetRestoredMarkup()

        await this.loadExtensions(payload, framework, DataTable)

        this.dispatchEvent('pre-init', { config: payload, DataTable })

        let apiPlatformAdapter: ApiPlatformAdapter | null = null
        if (this.isApiPlatformEnabled(payload)) {
            apiPlatformAdapter = new ApiPlatformAdapter(payload.columns)
            apiPlatformAdapter.configure(payload)
        }

        this.configureColumns(payload)

        if (
            hasLucideIcons(payload.columns) ||
            hasLucideIconsInActions(payload.bulkActions?.actions) ||
            typeof payload.emptyState?.icon === 'string'
        ) {
            await loadLucideIcons()
        }

        const urlStateCfg = isUrlStateEnabled(payload)
        if (urlStateCfg) {
            applyUrlStateToPayload(payload, readUrlState(urlStateCfg))
        }

        let requestParams: RequestParamsHandle | null = null
        const filterBar = installFilterBar(payload, DataTable, framework)
        const bulkBar = installBulkActionBar(
            payload,
            DataTable,
            framework,
            (name, detail) => this.dispatchEvent(name, detail),
            () => requestParams?.current() ?? {}
        )

        // Tables with none of these keep DataTables' own `ajax.data` handling untouched.
        if (filterBar || bulkBar || apiPlatformAdapter) {
            requestParams = installRequestParams(payload, {
                filters: filterBar ? () => filterBar.collectValues() : undefined,
                transport: apiPlatformAdapter
                    ? (params) => apiPlatformAdapter.toRequestParams(params)
                    : undefined,
            })
        }

        await applyTableLanguage(payload)

        // When installRequestParams owns the Ajax data hook, prefer its protocol snapshot over
        // ajax.params(): after an API Platform transport (or a JSON-string ajax.data callback)
        // the wire snapshot is not DataTables protocol, and the PHP export endpoint would drop
        // search/filters. Tables without that hook keep DataTables' own ajax.params().
        applyServerExportUrls(payload, requestParams ? () => requestParams.current() : undefined)
        applyCustomButtonActions(payload)

        // A concurrent connect may have built the table while this one awaited. Nothing is awaited
        // between this check and the constructor.
        if (this.adoptLiveTable(DataTable)) {
            return
        }

        const showSummaries =
            hasSummaries(payload) && ensureSummaryFooter(this.element as HTMLElement, payload)

        this.table = new DataTable(this.element as HTMLElement, payload) as DataTableWithAjax

        const themedContainer = (this.element as HTMLElement).closest('.dt-container')

        if (themedContainer && detectTheme() !== null) {
            applyThemeSearchPlaceholder(themedContainer)
        }

        if (showSummaries) {
            installColumnSummaries(this.table, payload)
        }

        this.dispatchEvent('connect', { table: this.table })

        if (urlStateCfg && this.table) {
            this.table.on('draw.dt', () => writeUrlState(urlStateCfg, this.table!))
            this.bindPopstate(urlStateCfg)
        }

        bindImagePlaceholder(this.element as HTMLElement)
        installRowActions(payload, {
            element: this.element as HTMLElement,
            framework: this.framework,
            getTable: () => this.table,
            dispatch: (name, detail) => this.dispatchEvent(name, detail),
        })

        bindRowFeatures(payload, this.element as HTMLElement, () => this.table, filterBar)

        this.isDataTableInitialized = true

        this.initPolling(payload)

        await this.startMercure(payload)
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onTurboBeforeCache)

        this.eventSource?.close()
        this.eventSource = null

        this.stopPolling?.()
        this.stopPolling = null

        this.highlighter?.destroy()
        this.highlighter = null

        if (this.popstateHandler) {
            window.removeEventListener('popstate', this.popstateHandler)
            this.popstateHandler = null
        }
    }

    /**
     * Binds this controller to a DataTable that already lives on the element instead of letting
     * `new DataTable()` fail with "Cannot reinitialise DataTable".
     */
    private adoptLiveTable(DataTable: any): boolean {
        if (!DataTable.isDataTable(this.element)) {
            return false
        }

        this.isDataTableInitialized = true
        this.table = new DataTable.Api(this.element) as DataTableWithAjax
        this.dispatchEvent('reconnect', { table: this.table })
        this.bindPopstate(isUrlStateEnabled(this.viewValue))
        this.initPolling(this.viewValue)

        return true
    }

    private bindPopstate(cfg: UrlStateConfig | null | false): void {
        // A detached controller never gets a disconnect() to remove the listener.
        if (!cfg || this.popstateHandler || !this.element.isConnected) {
            return
        }

        this.popstateHandler = () => this.applyUrlStateToTable(cfg)
        window.addEventListener('popstate', this.popstateHandler)
    }

    private applyUrlStateToTable(cfg: UrlStateConfig): void {
        if (!this.table) return

        const snap = readUrlState(cfg)

        if (snap.search !== undefined) this.table.search(snap.search as string)
        if (snap.order !== undefined) this.table.order(snap.order as any)
        if (snap.pageLength !== undefined) this.table.page.len(snap.pageLength)
        if (snap.start !== undefined) {
            const pageLen = this.table.page.len() as number
            this.table.page(Math.floor(snap.start / (pageLen || 10)))
        }

        this.table.draw(false)
    }

    /**
     * `render_datatable()` emits an empty `<table>`: every header cell, row, and control is built
     * by DataTables at init time. A browser history restore - a real Back/Forward that reloads the
     * document rather than a Turbo visit - can hand that generated markup back while every
     * JavaScript-side handle is gone: `turbo:before-cache` never fired, so nothing tore the table
     * down, and `DataTable.isDataTable()` reports false because DataTables' settings array died
     * with the previous document. Initializing over that half-processed DOM makes DataTables adopt
     * the stale header cells, whose count no longer matches the payload's columns, and it then
     * fails inside its own index-keyed column lookups (visible as a `TypeError` raised from
     * Buttons' `_columnText`, which is simply the first consumer to read a column that does not
     * exist).
     *
     * Emptying the table restores exactly what the server rendered, so initialization proceeds as
     * on a fresh load.
     */
    private resetRestoredMarkup(): void {
        const element = this.element as HTMLTableElement

        if (!element.classList.contains('dataTable') && element.childElementCount === 0) {
            return
        }

        // A surviving wrapper is emptied in place rather than unwrapped: reparenting the table
        // would make Stimulus report disconnect + reconnect, and the fresh controller instance
        // would race this initialization into a second `new DataTable()` on the same node.
        // Nothing in the DOM can prove ownership of the wrapper - every marker DataTables writes,
        // an application is free to write too - so the removal is inverted instead: a sibling goes
        // only when it carries positive evidence DataTables built it. An unrecognized node is left
        // alone, which at worst leaves a dead control on screen rather than deleting page content.
        const container = this.findGeneratedWrapper(element)
        if (container) {
            for (const child of Array.from(container.children)) {
                if (!child.contains(element) && this.isGeneratedMarkup(child, element.id)) {
                    child.remove()
                }
            }
        }

        element.replaceChildren()
        // DataTables re-adds this at init; removing it keeps the "already processed" signal
        // honest for anything - including this method on a later cycle - that reads it.
        element.classList.remove('dataTable')
    }

    /**
     * `dt-container` is only a class name: an application is free to put the table inside its own
     * element carrying it, and emptying that would delete controls and content the bundle never
     * generated. DataTables names the wrapper it builds after the table it wraps
     * (`<table id>_wrapper`), and it inserts that wrapper directly around the table, so the nearest
     * `.dt-container` ancestor is ours only when its id matches. Anything else is left alone.
     */
    private findGeneratedWrapper(element: HTMLTableElement): Element | null {
        if (!element.id) {
            return null
        }

        const container = element.closest('.dt-container')

        return container?.id === `${element.id}_wrapper` ? container : null
    }

    /**
     * Positive evidence that DataTables generated `node`: one of its own markup markers, or an id
     * it namespaces to the table (`<table id>_processing`, `<table id>_info`). Absence of evidence
     * means the node is left in place.
     */
    private isGeneratedMarkup(node: Element, tableId: string): boolean {
        if (
            node.matches(GENERATED_MARKUP_SELECTOR) ||
            node.querySelector(GENERATED_MARKUP_SELECTOR)
        ) {
            return true
        }

        const prefix = `${tableId}_`

        return (
            node.id.startsWith(prefix) ||
            Array.from(node.querySelectorAll('[id]')).some((el) => el.id.startsWith(prefix))
        )
    }

    private async loadExtensions(
        payload: DataTableView,
        framework: StyleFramework,
        DataTable: any
    ): Promise<void> {
        if (this.hasButtonsInLayout(payload)) {
            const { loadButtonsLibrary } = await import('./functions/loadButtonsLibrary.js')
            await loadButtonsLibrary(DataTable, framework, payload.layout)
        }

        for (const [payloadKey, extensionName] of Object.entries(EXTENSION_MAP)) {
            if (payload?.[payloadKey]) {
                await packageLoaders.load(extensionName, framework)
            }
        }

        if (isRecord(payload.select) && payload.select.withCheckbox) {
            payload.columns.unshift({
                data: null,
                defaultContent: '',
                name: null,
                orderable: false,
                searchable: false,
                title: '',
            })
            payload.order = shiftOrderForSelectColumn(payload.order)
            payload.columnDefs = [
                {
                    orderable: false,
                    render: DataTable.render.select(),
                    targets: 0,
                },
                ...(Array.isArray(payload.columnDefs) ? payload.columnDefs : []),
            ]
        }

        restrictSearchBuilder(payload)
    }

    private configureColumns(payload: DataTableView): void {
        normalizeDisabledColumnControls(payload)

        const style = resolveColumnStyleAdapter(this.framework, detectTheme())

        const columnRenderers: ColumnRenderer[] = [
            createBooleanColumnRenderer(
                payload.rowActionUrls?.toggle ?? '',
                this.areMutationsEnabled(payload) &&
                    typeof payload.dataTable === 'string' &&
                    payload.dataTable.length > 0,
                style
            ),
            createChoiceColumnRenderer(style),
            emailColumnRenderer,
            moneyColumnRenderer,
            relativeDateColumnRenderer,
            imageColumnRenderer,
            urlColumnRenderer,
            createIconColumnRenderer(style),
            createActionColumnRenderer(this.areMutationsEnabled(payload)),
            // Last: only string/string-utf8 columns without a renderer, so html()
            // markup and the specialized renderers above keep control of display.
            textColumnRenderer,
        ]

        payload.columns.forEach((column: any): void => {
            for (const renderer of isFormatted(column) ? [textColumnRenderer] : columnRenderers) {
                if (renderer.matches(column)) {
                    renderer.configure(column)
                }
            }
        })

        if (this.isApiPlatformEnabled(payload)) {
            payload.columns = payload.columns.map((column) => ({
                ...column,
                data: resolveColumnDataKey(column),
            }))
        }
    }

    private async startMercure(payload: DataTableView): Promise<void> {
        try {
            await this.initMercure(payload)
        } catch (error) {
            console.error('UX DataTables: unable to start the Mercure subscription.', error)
        }
    }

    private async initMercure(payload: DataTableView): Promise<void> {
        if (this.eventSource || !this.isMercureEnabled(payload)) {
            return
        }

        await this.initHighlighter(payload)

        const { createMercureSubscription } = await import('./functions/mercureSubscription.js')

        // Detached or subscribed while the modules loaded: disconnect() had no socket to close.
        if (!this.element.isConnected || this.eventSource) {
            return
        }

        this.eventSource = createMercureSubscription(payload.mercure, (event) => {
            this.dispatchEvent('mercure:message', { data: event.data, event })
            // Armed before the reload so the diff compares against what the viewer is looking at,
            // and run from the reload callback so an unrelated draw cannot consume the snapshot.
            this.highlighter?.arm()
            this.table?.ajax?.reload(() => this.highlighter?.diff(), false)
        })
    }

    private initPolling(payload: DataTableView): void {
        this.stopPolling = ensurePolling(this.stopPolling, {
            table: this.table,
            payload,
            element: this.element as HTMLElement,
            mercureEnabled: this.isMercureEnabled(payload),
        })
    }

    private async initHighlighter(payload: DataTableView): Promise<void> {
        if (!this.table || !isHighlightEnabled(payload)) {
            return
        }

        const { UpdateHighlighter } = await import('./functions/highlightUpdates.js')

        if (!this.element.isConnected || this.highlighter) {
            return
        }

        this.highlighter = new UpdateHighlighter(
            this.table,
            payload.highlight,
            payload.columns,
            (cells) => this.dispatchEvent('highlight', { cells })
        )
    }

    private hasButtonsInLayout(payload: DataTableView): boolean {
        const layout = payload?.layout
        if (!layout) return false

        return Object.values(layout).some((value) => {
            if (value === 'buttons') return true
            if (typeof value === 'object' && value !== null) {
                if (Array.isArray(value)) {
                    return value.some(
                        (v) =>
                            v === 'buttons' ||
                            (typeof v === 'object' && v !== null && 'buttons' in v)
                    )
                }
                if ('buttons' in value) return true
            }
            return false
        })
    }

    private dispatchEvent(name: string, payload: any) {
        return this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        })
    }

    private isApiPlatformEnabled(payload: DataTableView): boolean {
        return isApiPlatformAdapterEnabled(payload)
    }

    private isMercureEnabled(
        payload: DataTableView
    ): payload is DataTableView & { mercure: MercureConfig } {
        return !!payload?.mercure?.hubUrl && this.getMercureTopics(payload).length > 0
    }

    private areMutationsEnabled(payload: DataTableView): boolean {
        return payload?.mutationsEnabled === true
    }

    private getMercureTopics(payload: DataTableView): string[] {
        const topics = payload?.mercure?.topics

        if (Array.isArray(topics) && topics.length > 0) {
            return topics.filter(
                (topic: unknown): topic is string => typeof topic === 'string' && topic.length > 0
            )
        }

        return []
    }
}

type DataTableWithAjax = {
    ajax?: {
        reload: (callback?: (() => void) | null, resetPaging?: boolean) => void
    }
    on: (event: string, callback: (...args: any[]) => void) => DataTableWithAjax
    off: (event: string, callback?: (...args: any[]) => void) => DataTableWithAjax
    rows: (selector?: any) => {
        indexes: () => { toArray: () => number[] }
        ids: () => { toArray: () => unknown[] }
        deselect: () => void
    }
    cell: (rowIdx: number, colIdx: number) => { node: () => HTMLElement | null }
    table?: () => { container: () => HTMLElement | null }
    search: (input: string) => DataTableWithAjax
    order: (order: any) => DataTableWithAjax
    page: ((page?: number) => DataTableWithAjax) & { len: (len?: number) => number }
    draw: (resetPaging?: boolean | string) => DataTableWithAjax
    destroy: (remove?: boolean) => void
    row: (selector: any) => {
        data: () => Record<string, any>
        child: ((content?: any) => { show: () => void; hide: () => void }) & {
            isShown: () => boolean
            show: () => void
            hide: () => void
        }
    }
}
