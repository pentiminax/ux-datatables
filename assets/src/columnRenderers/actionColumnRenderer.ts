import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js'
import { ActionConfig, ActionRowData, ColumnRenderer } from './types.js'

export const actionColumnRenderer: ColumnRenderer = {
  matches(column: Record<string, any>): boolean {
    return Array.isArray(column?.actions)
  },

  configure(column: Record<string, any>): void {
    const actions = column.actions as ActionConfig[]

    column.render = (data: any, type: string, row: Record<string, any>): string => {
      if (type !== 'display') {
        return ''
      }

      return actions
        .filter((action) => {
          if (!action.displayCondition) {
            return true
          }

          const { field, value } = action.displayCondition
          return row[field] === value
        })
        .map((action) => {
          const idField = action.idField ?? 'id'
          const escapedId = escapeHtml(String(row[idField] ?? ''))
          const escapedEntity = escapeHtml(action.entityClass ?? '')
          const escapedLabel = escapeHtml(action.label)
          const escapedClassName = escapeHtml(action.className)
          const escapedType = escapeHtml(action.type)
          const iconHtml = action.icon ? `<i class="${escapeHtml(action.icon)}"></i> ` : ''

          if (action.type === 'DETAIL') {
            const href = resolveActionUrl(action, row as ActionRowData)

            if (!href || isUnsafeUrl(href)) {
              return ''
            }

            const attrs = [`class="${escapedClassName}"`, `href="${escapeHtml(href)}"`, `data-action-type="${escapedType}"`]

            if (action.confirm) {
              attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`)
            }

            return `<a ${attrs.join(' ')}>${iconHtml}${escapedLabel}</a>`
          }

          let attrs = `class="${escapedClassName}" data-action-type="${escapedType}" data-entity="${escapedEntity}" data-id="${escapedId}"`

          if (action.confirm) {
            attrs += ` data-confirm="${escapeHtml(action.confirm)}"`
          }

          return `<button ${attrs}>${iconHtml}${escapedLabel}</button>`
        })
        .filter(Boolean)
        .join(' ')
    }
  },
}

function resolveActionUrl(action: ActionConfig, row: ActionRowData): string | null {
  const resolvedUrl = row.__ux_datatables_actions?.[action.type]?.url

  if (typeof resolvedUrl === 'string' && resolvedUrl.trim().length > 0) {
    return resolvedUrl
  }

  if (typeof action.url === 'string' && action.url.trim().length > 0) {
    return action.url
  }

  return null
}
