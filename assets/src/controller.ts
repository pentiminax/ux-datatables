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
import { deleteRow } from './functions/deleteRow.js'
import { toggleBooleanValue } from './functions/toggleBooleanValue.js'
import { ApiPlatformAdapter, ColumnConfig } from './functions/apiPlatformAdapter.js'

export default class extends Controller {
  declare readonly viewValue: any

  static readonly values = {
    view: Object,
  }

  private table: DataTable<any> | null = null
  private isDataTableInitialized = false

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

    payload.columns.forEach((column: any): void => {
      if (this.isBooleanColumn(column)) {
        this.configureBooleanColumnRender(column)
      }

      if (this.isUrlColumn(column)) {
        this.configureUrlColumnRender(column)
      }

      if (column.action === 'DELETE') {
        column.render = function (data: any, type: string, row: any) {
          const className = `${column.action.toLowerCase()}-action`

          return `<button class="btn btn-danger ${className}" data-id="${row.id}" data-url="${column.actionUrl}">${column.actionLabel}</button>`
        }
      }
    })

    if (this.isApiPlatformEnabled(payload) && Array.isArray(payload.columns)) {
      payload.columns = (payload.columns as ColumnConfig[]).map((column) => ({
        ...column,
        data: column.field ?? column.data,
      }))
    }

    this.table = new DataTable(this.element as HTMLElement, payload)

    this.dispatchEvent('connect', { table: this.table })

    this.element.addEventListener('click', async (e: MouseEvent): Promise<void> => {
      const target = e.target as HTMLElement

      if (target.matches('.delete-action')) {
        const url: string | null = target.getAttribute('data-url')
        const id: string | null = target.getAttribute('data-id')

        if (url && id) {
          const response = await deleteRow({ url, id })

          if (response.ok) {
            this.table?.ajax.reload()
          }
        } else {
          console.error('Missing URL or ID for delete action')
        }
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

  private dispatchEvent(name: string, payload: any) {
    this.dispatch(name, {
      detail: payload,
      prefix: 'datatables',
    })
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

  private isBooleanColumn(column: Record<string, any>): boolean {
    return true === column?.booleanRenderAsSwitch
  }

  private configureBooleanColumnRender(column: Record<string, any>): void {
    const defaultState = true === column.booleanDefaultState
    const toggleUrl = this.getBooleanToggleUrl()
    const toggleMethod =
      typeof column.booleanToggleMethod === 'string' ? column.booleanToggleMethod : 'PATCH'
    const toggleIdField =
      typeof column.booleanToggleIdField === 'string' ? column.booleanToggleIdField : 'id'
    const toggleEntityClass =
      typeof column.booleanToggleEntityClass === 'string' ? column.booleanToggleEntityClass : ''

    column.type ??= 'num'
    column.render = (data: any, type: string, row: Record<string, any>): any => {
      const boolValue = this.parseBooleanValue(data, defaultState)

      if (type === 'sort' || type === 'type') {
        return boolValue ? 1 : 0
      }

      if (type === 'filter') {
        return boolValue ? 'ON' : 'OFF'
      }

      if (type !== 'display') {
        return boolValue ? 'ON' : 'OFF'
      }

      const rowId = row?.[toggleIdField]
      const checked = boolValue ? ' checked' : ''
      const disabled = toggleEntityClass === '' ? ' disabled' : ''
      const escapedId = this.escapeHtml(String(rowId ?? ''))
      const escapedUrl = this.escapeHtml(toggleUrl)
      const escapedField = this.escapeHtml(
        column.booleanToggleField ?? column.data ?? column.name ?? ''
      )
      const escapedMethod = this.escapeHtml(toggleMethod.toUpperCase())
      const escapedEntityClass = this.escapeHtml(toggleEntityClass)

      return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-entity="${escapedEntityClass}" data-method="${escapedMethod}"${checked}${disabled}></div>`
    }
  }

  private getBooleanToggleUrl(): string {
    return '/datatables/ajax/edit'
  }

  private parseBooleanValue(value: any, defaultValue: boolean = false): boolean {
    if (null === value || undefined === value || '' === value) {
      return defaultValue
    }

    if (typeof value === 'boolean') {
      return value
    }

    if (typeof value === 'number') {
      return value !== 0
    }

    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase()
      return ['1', 'true', 'yes', 'y', 'on'].includes(normalized)
    }

    return false
  }

  private isUrlColumn(column: Record<string, any>): boolean {
    return (
      typeof column?.urlTemplate === 'string' ||
      typeof column?.urlTarget === 'string' ||
      typeof column?.urlDisplayValue === 'string' ||
      true === column?.urlShowExternalIcon
    )
  }

  private configureUrlColumnRender(column: Record<string, any>): void {
    const urlTemplate = typeof column.urlTemplate === 'string' ? column.urlTemplate : null
    const routeParams =
      column.urlRouteParams && typeof column.urlRouteParams === 'object' ? column.urlRouteParams : null
    const target = typeof column.urlTarget === 'string' ? column.urlTarget : null
    const displayValue = typeof column.urlDisplayValue === 'string' ? column.urlDisplayValue : null
    const showExternalIcon = true === column.urlShowExternalIcon

    column.render = (data: any, type: string, row: Record<string, any>): any => {
      if (type !== 'display') {
        return data
      }

      let href: string
      if (urlTemplate && routeParams) {
        href = urlTemplate
        for (const [paramName, fieldName] of Object.entries(routeParams)) {
          const value = row[fieldName as string] ?? ''
          href = href.replace(`{${paramName}}`, encodeURIComponent(String(value)))
        }
      } else {
        href = typeof data === 'string' ? data : ''
      }

      if (this.isUnsafeUrl(href)) {
        return this.escapeHtml(String(data ?? ''))
      }

      const escapedHref = this.escapeHtml(href)
      const text = this.escapeHtml(displayValue ?? data ?? href)

      const attrs: string[] = [`href="${escapedHref}"`]

      if (target) {
        attrs.push(`target="${this.escapeHtml(target)}"`)
      }

      if (target === '_blank') {
        attrs.push('rel="noopener noreferrer"')
      }

      let html = `<a ${attrs.join(' ')}>${text}</a>`

      if (showExternalIcon) {
        html += ' <span aria-label="external link">&#x2197;</span>'
      }

      return html
    }
  }

  private isUnsafeUrl(url: string): boolean {
    const trimmed = url.trim().toLowerCase()
    return trimmed.startsWith('javascript:') || trimmed.startsWith('data:')
  }

  private escapeHtml(value: string): string {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }
}
