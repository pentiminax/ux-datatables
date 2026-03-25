import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js'
import { ActionConfig, ActionRowData, ColumnRenderer } from './types.js'

const SAFE_ATTRIBUTE_NAME_PATTERN = /^[a-zA-Z_:][a-zA-Z0-9:._-]*$/

export const actionColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return Array.isArray(column?.actions)
    },

    configure(column: Record<string, any>): void {
        const actions = column.actions as ActionConfig[]

        column.columnControl = []

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
                    const iconHtml = action.icon
                        ? `<i class="${escapeHtml(action.icon)}"></i> `
                        : ''

                    if (action.type === 'DETAIL') {
                        const href = resolveActionUrl(action, row as ActionRowData)

                        if (!href || isUnsafeUrl(href)) {
                            return ''
                        }

                        const attrs = [
                            `class="${escapedClassName}"`,
                            `href="${escapeHtml(href)}"`,
                            `data-action-type="${escapedType}"`,
                            ...serializeHtmlAttributes(
                                action.htmlAttributes,
                                new Set(['class', 'href', 'data-action-type', 'data-confirm'])
                            ),
                        ]

                        if (action.confirm) {
                            attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`)
                        }

                        return `<a ${attrs.join(' ')}>${iconHtml}${escapedLabel}</a>`
                    }

                    const attrs = [
                        `class="${escapedClassName}"`,
                        `data-action-type="${escapedType}"`,
                        `data-entity="${escapedEntity}"`,
                        `data-id="${escapedId}"`,
                        ...serializeHtmlAttributes(
                            action.htmlAttributes,
                            new Set([
                                'class',
                                'data-action-type',
                                'data-entity',
                                'data-id',
                                'data-confirm',
                            ])
                        ),
                    ]

                    if (action.confirm) {
                        attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`)
                    }

                    return `<button ${attrs.join(' ')}>${iconHtml}${escapedLabel}</button>`
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

function serializeHtmlAttributes(
    htmlAttributes: ActionConfig['htmlAttributes'],
    reservedAttributes: Set<string>
): string[] {
    if (!htmlAttributes) {
        return []
    }

    return Object.entries(htmlAttributes).flatMap(([name, value]) => {
        const normalizedName = name.toLowerCase()

        if (!SAFE_ATTRIBUTE_NAME_PATTERN.test(name) || reservedAttributes.has(normalizedName)) {
            return []
        }

        if (typeof value === 'boolean') {
            return value ? [name] : []
        }

        if (null === value || undefined === value) {
            return []
        }

        return [`${name}="${escapeHtml(String(value))}"`]
    })
}
