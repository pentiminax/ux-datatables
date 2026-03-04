import { escapeHtml, parseBooleanValue } from '../functions/htmlUtils.js'
import { BooleanCustomOptions, ColumnRenderer } from './types.js'

export function createBooleanColumnRenderer(toggleUrl: string): ColumnRenderer {
  return {
    matches(column: Record<string, any>): boolean {
      return true === column?.customOptions?.renderAsSwitch
    },

    configure(column: Record<string, any>): void {
      const customOptions = (column.customOptions ?? {}) as BooleanCustomOptions
      const defaultState = true === customOptions.defaultState
      const toggleMethod = customOptions.toggleMethod ?? 'PATCH'
      const toggleIdField = customOptions.toggleIdField ?? 'id'
      const entityClass =
        typeof customOptions.entityClass === 'string' ? customOptions.entityClass : ''

      column.type ??= 'num'

      column.render = (data: any, type: string, row: Record<string, any>): any => {
        const boolValue = parseBooleanValue(data, defaultState)

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
        const disabled = entityClass === '' ? ' disabled' : ''
        const escapedId = escapeHtml(String(rowId ?? ''))
        const escapedUrl = escapeHtml(toggleUrl)
        const escapedField = escapeHtml(
          customOptions.toggleField ?? column.field ?? column.data ?? column.name ?? ''
        )
        const escapedMethod = escapeHtml(toggleMethod.toUpperCase())
        const escapedEntityClass = escapeHtml(entityClass)

        return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-entity="${escapedEntityClass}" data-method="${escapedMethod}"${checked}${disabled}></div>`
      }
    },
  }
}
