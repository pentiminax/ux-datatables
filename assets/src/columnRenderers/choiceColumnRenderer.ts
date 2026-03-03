import { escapeHtml } from '../functions/htmlUtils.js'
import { ColumnRenderer } from './types.js'

export const choiceColumnRenderer: ColumnRenderer = {
  matches(column: Record<string, any>): boolean {
    return typeof column?.choices === 'object' && column.choices !== null
  },

  configure(column: Record<string, any>): void {
    const choices = (column.choices ?? {}) as Record<string, string>
    const badgesEnabled =
      true === column.renderAsBadges ||
      (typeof column.renderAsBadges === 'object' && column.renderAsBadges !== null)
    const badges =
      typeof column.renderAsBadges === 'object' && column.renderAsBadges !== null
        ? (column.renderAsBadges as Record<string, string>)
        : {}
    const defaultBadgeVariant =
      typeof column.defaultBadgeVariant === 'string' ? column.defaultBadgeVariant : 'secondary'

    column.render = (data: any, type: string): any => {
      const key = String(data ?? '')
      const label = choices[key] ?? key

      if (type !== 'display') {
        return label
      }

      if (!badgesEnabled) {
        return escapeHtml(label)
      }

      const variant = badges[key] ?? defaultBadgeVariant
      const escapedLabel = escapeHtml(label)
      const escapedVariant = escapeHtml(variant)
      return `<span class="badge text-bg-${escapedVariant}">${escapedLabel}</span>`
    }
  },
}
