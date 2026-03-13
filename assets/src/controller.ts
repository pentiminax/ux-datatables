import { Controller } from '@hotwired/stimulus'
import DataTable from 'datatables.net/types/types'
import { getLoadedDataTablesStyleSheet } from './functions/getLoadedDataTablesStyleSheet.js'
import { loadButtonsLibrary } from './functions/loadButtonsLibrary.js'
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js'
import { ExtensionRegistry } from './functions/extensionRegistry.js'
import { toggleBooleanValue } from './functions/toggleBooleanValue.js'
import { ApiPlatformAdapter, ColumnConfig } from './functions/apiPlatformAdapter.js'
import { ColumnRenderer } from './columnRenderers/types.js'
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js'
import { choiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js'
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js'
import { actionColumnRenderer } from './columnRenderers/actionColumnRenderer.js'
import { deleteEntity } from './functions/deleteEntity.js'
import { fetchEditForm } from './functions/fetchEditForm.js'
import { submitEditForm } from './functions/submitEditForm.js'
import { createEditModal } from './functions/editModal.js'

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

        const stylesheet = getLoadedDataTablesStyleSheet()

        const DataTable = await loadDataTableLibrary(stylesheet)

        if (DataTable.isDataTable(this.element)) {
            this.isDataTableInitialized = true
            return
        }

        await this.loadExtensions(payload, stylesheet, DataTable)

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
        stylesheet: CSSStyleSheet | null,
        DataTable: any
    ): Promise<void> {
        if (this.isButtonsExtensionEnabled(payload)) {
            await loadButtonsLibrary(DataTable, stylesheet)
        }

        if (this.isSelectExtensionEnabled(payload)) {
            await ExtensionRegistry.load('select', stylesheet)
        }

        if (this.isResponsiveExtensionEnabled(payload)) {
            await ExtensionRegistry.load('responsive', stylesheet)
            if (payload.select?.withCheckbox) {
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
                ]
            }
        }

        if (this.isColumnControlExtensionEnabled(payload)) {
            await ExtensionRegistry.load('columnControl', stylesheet)
        }

        if (this.isFixedColumnsExtensionEnabled(payload)) {
            await ExtensionRegistry.load('fixedColumns', stylesheet)
        }

        if (this.isColReorderExtensionEnabled(payload)) {
            await ExtensionRegistry.load('colReorder', stylesheet)
        }

        if (this.isKeyTableExtensionEnabled(payload)) {
            await ExtensionRegistry.load('keyTable', stylesheet)
        }

        if (this.isScrollerExtensionEnabled(payload)) {
            await ExtensionRegistry.load('scroller', stylesheet)
        }
    }

    private configureColumns(payload: Record<string, any>): void {
        const columnRenderers: ColumnRenderer[] = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl()),
            choiceColumnRenderer,
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
                    const modal = createEditModal()

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

    private dispatchEvent(name: string, payload: any) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        })
    }

    private getBooleanToggleUrl(): string {
        return '/datatables/ajax/edit'
    }

    private isButtonsExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.layout?.topStart?.buttons
    }

    private isSelectExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.select
    }

    private isResponsiveExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.responsive
    }

    private isColumnControlExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.columnControl
    }

    private isFixedColumnsExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.fixedColumns
    }

    private isColReorderExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.colReorder
    }

    private isKeyTableExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.keys
    }

    private isScrollerExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.scroller
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
