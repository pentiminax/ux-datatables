import { Controller } from '@hotwired/stimulus'
import DataTable from 'datatables.net/types/types'
import { getLoadedDataTablesStyleSheet } from './functions/getLoadedDataTablesStyleSheet.js'
import { loadButtonsLibrary } from './functions/loadButtonsLibrary.js'
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js'
import { loadSelectLibrary } from './functions/loadSelectLibrary.js'
import { loadResponsiveLibrary } from './functions/loadResponsiveLibrary.js'
import { loadColReorderLibrary } from './functions/loadColReorderLibrary.js'
import { loadColumnControlLibrary } from './functions/loadColumnControlLibrary.js'
import { loadFixedColumnsLibrary } from './functions/loadFixedColumnsLibrary.js'
import { loadKeyTableLibrary } from './functions/loadKeyTableLibrary.js'
import { loadScrollerLibrary } from './functions/loadScrollerLibrary.js'
import { toggleBooleanValue } from './functions/toggleBooleanValue.js'
import { ApiPlatformAdapter, ColumnConfig } from './functions/apiPlatformAdapter.js'
import { ColumnRenderer } from './columnRenderers/types.js'
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js'
import { choiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js'
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js'
import { actionColumnRenderer } from './columnRenderers/actionColumnRenderer.js'
import { deleteEntity } from './functions/deleteEntity.js'

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

    if (this.isButtonsExtensionEnabled(payload)) {
      await loadButtonsLibrary(DataTable, stylesheet)
    }

    if (this.isSelectExtensionEnabled(payload)) {
      await loadSelectLibrary(stylesheet)
    }

    if (this.isResponsiveExtensionEnabled(payload)) {
      await loadResponsiveLibrary(stylesheet)
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
      await loadColumnControlLibrary(stylesheet)
    }

    if (this.isFixedColumnsExtensionEnabled(payload)) {
      await loadFixedColumnsLibrary(stylesheet)
    }

    if (this.isColReorderExtensionEnabled(payload)) {
      await loadColReorderLibrary(stylesheet)
    }

    if (this.isKeyTableExtensionEnabled(payload)) {
      await loadKeyTableLibrary(stylesheet)
    }

    if (this.isScrollerExtensionEnabled(payload)) {
      await loadScrollerLibrary(stylesheet)
    }

    if (this.isApiPlatformEnabled(payload)) {
      const columns = Array.isArray(payload.columns) ? (payload.columns as ColumnConfig[]) : []
      new ApiPlatformAdapter(columns).configure(payload)
    }

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

    this.table = new DataTable(this.element as HTMLElement, payload) as DataTableWithAjax

    this.dispatchEvent('connect', { table: this.table })

    if (this.isMercureEnabled(payload)) {
      const { createMercureSubscription } = await import('./functions/mercureSubscription.js')
      this.eventSource = createMercureSubscription(payload.mercure, (event) => {
        this.dispatchEvent('mercure:message', { data: event.data, event })
        this.table?.ajax?.reload(null, false)
      })
    }

    this.element.addEventListener('click', async (e: MouseEvent): Promise<void> => {
      const target = e.target as HTMLElement
      const actionButton = target.closest('[data-action-type]') as HTMLElement | null

      if (actionButton) {
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

        return
      }
    })

    this.element.addEventListener('change', async (e: Event): Promise<void> => {
      const target = e.target as EventTarget | null
      if (!(target instanceof HTMLInputElement) || !target.matches('.boolean-switch-action')) {
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

    this.isDataTableInitialized = true
  }

  disconnect() {
    this.eventSource?.close()
    this.eventSource = null
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
