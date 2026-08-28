import { Controller } from '@hotwired/stimulus'
import { createActionColumnRenderer } from './columnRenderers/actionColumnRenderer.js'
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js'
import { createChoiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js'
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js'
import { createIconColumnRenderer } from './columnRenderers/iconColumnRenderer.js'
import { imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js'
import { moneyColumnRenderer } from './columnRenderers/moneyColumnRenderer.js'
import type { ColumnRenderer } from './columnRenderers/types.js'
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js'
import { resolveColumnStyleAdapter } from './columnStyles/resolveColumnStyleAdapter.js'
import {
    ApiPlatformAdapter,
    type ColumnConfig,
    resolveColumnDataKey,
} from './functions/apiPlatformAdapter.js'
import { applyCustomButtonActions } from './functions/applyCustomButtonActions.js'
import { applyServerExportUrls } from './functions/serverExport.js'
import { normalizeDisabledColumnControls } from './functions/columnControl.js'
import { deleteEntity } from './functions/deleteEntity.js'
import { detectStyleFramework } from './functions/detectStyleFramework.js'
import { ExtensionRegistry } from './functions/extensionRegistry.js'
import { fetchDetailRow } from './functions/fetchDetailRow.js'
import { fetchEditForm } from './functions/fetchEditForm.js'
import { registerFilterFeature } from './functions/filterFeature.js'
import { applyFilterLayout } from './functions/filterLayout.js'
import { FilterBar, hasFilters } from './functions/filters.js'
import { isFixedHeaderClone } from './functions/isFixedHeaderClone.js'
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js'
import { applyLocalLanguage } from './functions/localLanguage.js'
import { hasLucideIcons, loadLucideIcons } from './functions/lucideIcons.js'
import { runAjaxAction } from './functions/runAjaxAction.js'
import { submitEditForm } from './functions/submitEditForm.js'
import { toggleBooleanValue } from './functions/toggleBooleanValue.js'
import {
    applyUrlStateToPayload,
    isUrlStateEnabled,
    readUrlState,
    type UrlStateConfig,
    writeUrlState,
} from './functions/urlState.js'
import { resolveModalAdapter } from './modal/resolveModalAdapter.js'
import { isStyleFramework, type StyleFramework } from './types/styleFramework.js'

/**
 * Maps DataTables payload property keys to their corresponding extension names
 * in the ExtensionRegistry.
 *
 * The payload key 'keys' maps to the 'keyTable' extension — this is an intentional
 * naming difference between the DataTables config API ('keys') and the extension
 * package name ('keyTable').
 */
const EXTENSION_MAP: Record<string, string> = {
    select: 'select',
    responsive: 'responsive',
    columnControl: 'columnControl',
    fixedColumns: 'fixedColumns',
    fixedHeader: 'fixedHeader',
    colReorder: 'colReorder',
    keys: 'keyTable',
    rowGroup: 'rowGroup',
    scroller: 'scroller',
}

export default class extends Controller {
    declare readonly viewValue: any

    static readonly values = {
        view: Object,
    }

    private table: DataTableWithAjax | null = null
    private isDataTableInitialized = false
    private eventSource: EventSource | null = null
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

        if (isFixedHeaderClone(this.element)) {
            return
        }

        // Registered before the initialized guard on purpose. DataTables' own DOM work reparents
        // the table, which Stimulus reports as disconnect + reconnect on a live table; the
        // reconnect early-returns below, so registering after the guard would drop this listener
        // for good on the first such cycle. A stable arrow property lets the DOM deduplicate
        // repeat registrations of the identical listener.
        document.addEventListener('turbo:before-cache', this.onTurboBeforeCache)

        if (this.isDataTableInitialized) {
            return
        }

        const payload = this.viewValue

        this.dispatchEvent('pre-connect', {
            config: payload,
        })

        const framework = isStyleFramework(payload.styleFramework)
            ? payload.styleFramework
            : detectStyleFramework()
        this.framework = framework

        const DataTable = await loadDataTableLibrary(framework)
        registerFilterFeature(DataTable)

        if (DataTable.isDataTable(this.element)) {
            this.isDataTableInitialized = true
            return
        }

        await this.loadExtensions(payload, framework, DataTable)

        this.dispatchEvent('pre-init', { config: payload, DataTable })

        if (this.isApiPlatformEnabled(payload)) {
            const columns = Array.isArray(payload.columns)
                ? (payload.columns as ColumnConfig[])
                : []
            new ApiPlatformAdapter(columns).configure(payload)
        }

        this.configureColumns(payload)

        if (hasLucideIcons(payload.columns)) {
            await loadLucideIcons()
        }

        const urlStateCfg = isUrlStateEnabled(payload)
        if (urlStateCfg) {
            applyUrlStateToPayload(payload, readUrlState(urlStateCfg))
        }

        if (hasFilters(payload)) {
            const filterBar = new FilterBar(payload, framework)
            filterBar.attachToPayload(payload)
            applyFilterLayout(payload, filterBar)
        }

        await applyLocalLanguage(payload)

        applyServerExportUrls(payload)
        applyCustomButtonActions(payload)

        this.table = new DataTable(this.element as HTMLElement, payload) as DataTableWithAjax

        this.dispatchEvent('connect', { table: this.table })

        if (urlStateCfg && this.table) {
            this.table.on('draw.dt', () => writeUrlState(urlStateCfg, this.table!))
            this.popstateHandler = () => this.applyUrlStateToTable(urlStateCfg)
            window.addEventListener('popstate', this.popstateHandler)
        }

        await this.initMercure(payload)
        this.bindActionHandler(payload)
        this.bindBooleanToggleHandler(payload)

        this.isDataTableInitialized = true
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onTurboBeforeCache)

        this.eventSource?.close()
        this.eventSource = null

        if (this.popstateHandler) {
            window.removeEventListener('popstate', this.popstateHandler)
            this.popstateHandler = null
        }
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

    private async loadExtensions(
        payload: Record<string, any>,
        framework: StyleFramework,
        DataTable: any
    ): Promise<void> {
        if (this.hasButtonsInLayout(payload)) {
            const { loadButtonsLibrary } = await import('./functions/loadButtonsLibrary.js')
            await loadButtonsLibrary(DataTable, framework)
        }

        for (const [payloadKey, extensionName] of Object.entries(EXTENSION_MAP)) {
            if (payload?.[payloadKey]) {
                await ExtensionRegistry.load(extensionName, framework)
            }
        }

        if (payload?.select?.withCheckbox) {
            payload.columns.unshift({
                data: null,
                defaultContent: '',
                name: null,
                orderable: false,
                searchable: false,
                title: '',
            })
            payload.columnDefs = [
                {
                    orderable: false,
                    render: DataTable.render.select(),
                    targets: 0,
                },
                ...(payload.columnDefs ?? []),
            ]
        }
    }

    private configureColumns(payload: Record<string, any>): void {
        normalizeDisabledColumnControls(payload)

        const style = resolveColumnStyleAdapter(this.framework)

        const columnRenderers: ColumnRenderer[] = [
            createBooleanColumnRenderer(
                this.getBooleanToggleUrl(),
                this.areMutationsEnabled(payload) &&
                    typeof payload.dataTable === 'string' &&
                    payload.dataTable.length > 0,
                style
            ),
            createChoiceColumnRenderer(style),
            emailColumnRenderer,
            moneyColumnRenderer,
            imageColumnRenderer,
            urlColumnRenderer,
            createIconColumnRenderer(style),
            createActionColumnRenderer(this.areMutationsEnabled(payload)),
        ]

        payload.columns.forEach((column: any): void => {
            for (const renderer of columnRenderers) {
                if (renderer.matches(column)) {
                    renderer.configure(column)
                }
            }
        })

        if (this.isApiPlatformEnabled(payload) && Array.isArray(payload.columns)) {
            payload.columns = (payload.columns as ColumnConfig[]).map((column) => ({
                ...column,
                data: resolveColumnDataKey(column),
            }))
        }
    }

    private async initMercure(payload: Record<string, any>): Promise<void> {
        if (this.isMercureEnabled(payload)) {
            const { createMercureSubscription } = await import('./functions/mercureSubscription.js')
            this.eventSource = createMercureSubscription(payload.mercure, (event) => {
                this.dispatchEvent('mercure:message', { data: event.data, event })
                this.table?.ajax?.reload(null, false)
            })
        }
    }

    private bindActionHandler(payload: Record<string, any>): void {
        ;(this.element as HTMLElement).addEventListener(
            'click',
            async (e: MouseEvent): Promise<void> => {
                const target = e.target as HTMLElement
                const actionButton = target.closest('[data-action-type]') as HTMLElement | null

                if (!actionButton) {
                    return
                }

                const actionType = actionButton.getAttribute('data-action-type')
                const id = actionButton.getAttribute('data-id')
                const dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : ''
                const confirmMessage = actionButton.getAttribute('data-confirm')

                if (confirmMessage && !confirm(confirmMessage)) {
                    e.preventDefault()
                    return
                }

                const ajaxMethod = actionButton.getAttribute('data-ajax-method')

                if (ajaxMethod) {
                    e.preventDefault()
                    await this.executeAjaxAction(actionButton, ajaxMethod, payload)

                    return
                }

                if (actionType === 'DETAIL' && dataTable && id) {
                    e.preventDefault()

                    const rowElement = actionButton.closest('tr')
                    const row = rowElement ? this.table?.row(rowElement) : null

                    if (!row) {
                        return
                    }

                    if (row.child.isShown()) {
                        row.child.hide()
                        actionButton.classList.remove('expanded')
                        return
                    }

                    const result = await fetchDetailRow({ dataTable, id })

                    if (result.success) {
                        row.child(result.html).show()
                        actionButton.classList.add('expanded')
                    }
                }

                if (actionType === 'DELETE' && dataTable && id) {
                    e.preventDefault()
                    const response = await deleteEntity({
                        dataTable,
                        id,
                        csrfToken: this.getCsrfToken(payload),
                    })

                    if (response.ok) {
                        this.table?.ajax?.reload(null, false)
                    }
                }

                if (actionType === 'EDIT' && dataTable && id) {
                    e.preventDefault()
                    const modalConfig = payload.editModal ?? {}
                    const modal = await resolveModalAdapter(
                        modalConfig.adapter ?? null,
                        this.framework
                    )
                    if (!modal) return

                    const result = await fetchEditForm({ dataTable, id })

                    if (result.success) {
                        await modal.show(result.html, {
                            onSubmit: async (formData) => {
                                const submitResult = await submitEditForm({
                                    dataTable,
                                    id,
                                    formData,
                                    csrfToken: this.getCsrfToken(payload),
                                })

                                if (submitResult.success) {
                                    await modal.hide()
                                    this.table?.ajax?.reload(null, false)
                                } else if (submitResult.html) {
                                    modal.replaceBody(submitResult.html)
                                }
                            },
                        })
                    }
                }
            }
        )
    }

    private async executeAjaxAction(
        button: HTMLElement,
        method: string,
        payload: Record<string, any>
    ): Promise<void> {
        const url = button.getAttribute('data-ajax-url')
        const token = button.getAttribute('data-ajax-token')

        if (!url || !token) {
            return
        }

        await runAjaxAction({
            button,
            method,
            url,
            token,
            dispatch: (name, detail) => this.dispatchEvent(name, detail),
            navigate: (target) => window.location.assign(target),
            reload: () => {
                if (payload.ajax) {
                    this.table?.ajax?.reload(null, false)

                    return
                }

                window.location.reload()
            },
        })
    }

    private bindBooleanToggleHandler(payload: Record<string, any>): void {
        this.element.addEventListener('change', async (e: Event): Promise<void> => {
            const target = e.target as EventTarget | null
            if (
                !(target instanceof HTMLInputElement) ||
                !target.matches('.boolean-switch-action')
            ) {
                return
            }

            const url = target.dataset.url
            const id = target.dataset.id
            const field = target.dataset.field
            const method = target.dataset.method ?? 'PATCH'
            const dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : ''

            if (!id || !field) {
                target.checked = !target.checked
                console.error('Missing ID or field for boolean switch update')
                return
            }

            if (!dataTable) {
                target.checked = !target.checked
                console.error('Missing DataTable token for boolean toggle endpoint')

                return
            }

            const previousState = !target.checked

            target.disabled = true

            try {
                const response = await toggleBooleanValue({
                    url: url ?? this.getBooleanToggleUrl(),
                    id,
                    field,
                    newValue: target.checked,
                    method,
                    dataTable,
                    csrfToken: this.getCsrfToken(payload),
                })

                if (!response.ok) {
                    target.checked = previousState
                    console.error(`Boolean switch update failed with status ${response.status}`)
                }
            } catch (error) {
                target.checked = previousState
                console.error('Boolean switch update failed', error)
            } finally {
                target.disabled = false
            }
        })
    }

    private hasButtonsInLayout(payload: Record<string, any>): boolean {
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
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        })
    }

    private getBooleanToggleUrl(): string {
        return '/datatables/ajax/edit'
    }

    private isApiPlatformEnabled(payload: Record<string, any>): boolean {
        return true === payload?.apiPlatform
    }

    private isMercureEnabled(payload: Record<string, any>): boolean {
        return !!payload?.mercure?.hubUrl && this.getMercureTopics(payload).length > 0
    }

    private getCsrfToken(payload: Record<string, any>): string | undefined {
        const token = payload?.csrfToken
        return typeof token === 'string' && token.length > 0 ? token : undefined
    }

    private areMutationsEnabled(payload: Record<string, any>): boolean {
        return payload?.mutationsEnabled === true
    }

    private getMercureTopics(payload: Record<string, any>): string[] {
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
        reload: (callback?: null, resetPaging?: boolean) => void
    }
    on: (event: string, callback: (...args: any[]) => void) => DataTableWithAjax
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
