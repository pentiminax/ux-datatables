import { Controller } from '@hotwired/stimulus'
import type DataTable from 'datatables.net/types/types'
import { actionColumnRenderer } from './columnRenderers/actionColumnRenderer.js'
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js'
import { choiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js'
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js'
import type { ColumnRenderer } from './columnRenderers/types.js'
import { imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js'
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js'
import { ApiPlatformAdapter, type ColumnConfig } from './functions/apiPlatformAdapter.js'
import { deleteEntity } from './functions/deleteEntity.js'
import { detectStyleFramework } from './functions/detectStyleFramework.js'
import { createEditModal } from './functions/editModal.js'
import { ExtensionRegistry } from './functions/extensionRegistry.js'
import { fetchEditForm } from './functions/fetchEditForm.js'
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js'
import { submitEditForm } from './functions/submitEditForm.js'
import { toggleBooleanValue } from './functions/toggleBooleanValue.js'
import type { StyleFramework } from './types/styleFramework.js'

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
    colReorder: 'colReorder',
    keys: 'keyTable',
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

    async connect() {
        if (this.isDataTableInitialized) {
            return
        }

        if (!(this.element instanceof HTMLTableElement)) {
            throw new Error('Invalid element')
        }

        const payload = this.viewValue

        this.dispatchEvent('pre-connect', {
            config: payload,
        })

        const framework = detectStyleFramework()

        const DataTable = await loadDataTableLibrary(framework)

        if (DataTable.isDataTable(this.element)) {
            this.isDataTableInitialized = true
            return
        }

        await this.loadExtensions(payload, framework, DataTable)

        if (this.isApiPlatformEnabled(payload)) {
            const columns = Array.isArray(payload.columns)
                ? (payload.columns as ColumnConfig[])
                : []
            new ApiPlatformAdapter(columns).configure(payload)
        }

        this.configureColumns(payload)

        this.table = new DataTable(this.element as HTMLElement, payload) as DataTableWithAjax

        this.dispatchEvent('connect', { table: this.table })

        await this.initMercure(payload)
        this.bindActionHandler(payload)
        this.bindBooleanToggleHandler(payload)

        this.isDataTableInitialized = true
    }

    disconnect() {
        this.eventSource?.close()
        this.eventSource = null
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
        const columnRenderers: ColumnRenderer[] = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl()),
            choiceColumnRenderer,
            emailColumnRenderer,
            imageColumnRenderer,
            urlColumnRenderer,
            actionColumnRenderer,
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
                data: column.field ?? column.data,
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
                const entity = actionButton.getAttribute('data-entity')
                const id = actionButton.getAttribute('data-id')
                const confirmMessage = actionButton.getAttribute('data-confirm')

                if (confirmMessage && !confirm(confirmMessage)) {
                    e.preventDefault()
                    return
                }

                if (actionType === 'DELETE' && entity && id) {
                    e.preventDefault()
                    const response = await deleteEntity({
                        entity,
                        id,
                        topics: this.getMercureTopics(payload),
                    })

                    if (response.ok) {
                        this.table?.ajax?.reload(null, false)
                    }
                }

                if (actionType === 'EDIT' && entity && id) {
                    e.preventDefault()
                    const columns = this.getEditableColumns(payload)
                    const modal = await createEditModal()
                    if (!modal) return

                    const result = await fetchEditForm({ entity, id, columns })

                    if (result.success) {
                        modal.show(result.html, async (formData) => {
                            const submitResult = await submitEditForm({
                                entity,
                                id,
                                columns,
                                formData,
                                topics: this.getMercureTopics(payload),
                            })

                            if (submitResult.success) {
                                modal.hide()
                                this.table?.ajax?.reload(null, false)
                            } else if (submitResult.html) {
                                modal.showErrors(submitResult.html)
                            }
                        })
                    }
                }
            }
        )
    }

    private getEditableColumns(payload: Record<string, any>): Record<string, any>[] {
        if (!Array.isArray(payload.columns)) {
            return []
        }

        return payload.columns
            .filter((column: any) => {
                if (Array.isArray(column.actions)) {
                    return false
                }

                const custom = column.customOptions ?? {}

                if (custom.hideWhenUpdating) {
                    return false
                }

                if (custom.templatePath || custom.routeName || custom.template) {
                    return false
                }

                return true
            })
            .map((column: any) => ({
                name: column.name,
                title: column.title,
                type: column.type,
                field: column.field,
                customOptions: column.customOptions,
            }))
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
            const entity = target.dataset.entity
            const method = target.dataset.method ?? 'PATCH'

            if (!id || !field) {
                target.checked = !target.checked
                console.error('Missing ID or field for boolean switch update')
                return
            }

            if (!entity) {
                target.checked = !target.checked
                console.error('Missing entity for boolean toggle endpoint')

                return
            }

            const previousState = !target.checked

            target.disabled = true

            try {
                const response = await toggleBooleanValue({
                    url: url ?? this.getBooleanToggleUrl(),
                    id,
                    field,
                    entity,
                    newValue: target.checked,
                    method,
                    topics: this.getMercureTopics(payload),
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

type DataTableWithAjax = DataTable<any> & {
    ajax?: {
        reload: (callback?: null, resetPaging?: boolean) => void
    }
}
