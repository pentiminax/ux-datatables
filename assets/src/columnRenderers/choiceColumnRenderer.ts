import type { ColumnStyleAdapter } from '../columnStyles/ColumnStyleAdapter.js'
import { escapeHtml } from '../functions/htmlUtils.js'
import type { ChoiceCustomOptions, ColumnRenderer } from './types.js'

export function createChoiceColumnRenderer(style: ColumnStyleAdapter): ColumnRenderer {
    return {
        matches(column: Record<string, any>): boolean {
            return (
                typeof column?.customOptions?.choices === 'object' &&
                column.customOptions.choices !== null
            )
        },

        configure(column: Record<string, any>): void {
            const customOptions = (column.customOptions ?? {}) as ChoiceCustomOptions
            const choices = (customOptions.choices ?? {}) as Record<string, string>
            const badgesEnabled =
                true === customOptions.renderAsBadges ||
                (typeof customOptions.renderAsBadges === 'object' &&
                    customOptions.renderAsBadges !== null)
            const badges =
                typeof customOptions.renderAsBadges === 'object' &&
                customOptions.renderAsBadges !== null
                    ? (customOptions.renderAsBadges as Record<string, string>)
                    : {}
            const defaultBadgeVariant = customOptions.defaultBadgeVariant || 'secondary'

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

                return style.renderBadge(label, variant)
            }
        },
    }
}
