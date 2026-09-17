import { escapeHtml, isSameOriginUrl, isUnsafeUrl } from '../functions/htmlUtils.js'
import { renderLucideIcon } from '../functions/lucideIcons.js'
import type { ActionConfig, ActionRowData, ColumnRenderer } from './types.js'

const SAFE_ATTRIBUTE_NAME_PATTERN = /^[a-zA-Z_:][a-zA-Z0-9:._-]*$/

const DEFAULT_COLLAPSIBLE_ICON = '<span class="dtr-control-icon" aria-hidden="true">&#9656;</span> '

export function createActionColumnRenderer(mutationsEnabled = true): ColumnRenderer {
    return {
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
                    .filter(
                        (action) =>
                            !isDeniedAction(action, row as ActionRowData) ||
                            action.disabledWhenDenied === true
                    )
                    .filter((action) => {
                        if (!action.displayCondition) {
                            return true
                        }

                        const { field, value } = action.displayCondition
                        return row[field] === value
                    })
                    .map((action) => {
                        if (isDeniedAction(action, row as ActionRowData)) {
                            return renderDeniedAction(action)
                        }

                        const id = resolveActionId(action, row as ActionRowData)
                        const escapedId = escapeHtml(String(id ?? ''))
                        const escapedLabel = escapeHtml(action.label)
                        const escapedClassName = escapeHtml(action.className)
                        const escapedType = escapeHtml(action.type)
                        const iconHtml = renderActionIcon(action)

                        if (action.type === 'DETAIL' && action.collapsible) {
                            const iconMarkup = iconHtml || DEFAULT_COLLAPSIBLE_ICON

                            const attrs = [
                                `type="button"`,
                                `class="${escapedClassName}"`,
                                `data-action-type="${escapedType}"`,
                                `data-id="${escapedId}"`,
                                ...serializeHtmlAttributes(
                                    action.htmlAttributes,
                                    new Set([
                                        'type',
                                        'class',
                                        'data-action-type',
                                        'data-id',
                                    ])
                                ),
                            ]

                            return `<button ${attrs.join(' ')}>${iconMarkup}${escapedLabel}</button>`
                        }

                        const href = resolveActionUrl(action, row as ActionRowData)

                        if (action.ajaxMethod) {
                            const method = resolveAjaxMethod(action.ajaxMethod)
                            const token = row.__ux_datatables_actions?.[action.name]?.token

                            if (
                                !method ||
                                !token ||
                                !href ||
                                isUnsafeUrl(href) ||
                                !isSameOriginUrl(href)
                            ) {
                                return ''
                            }

                            const attrs = [
                                `type="button"`,
                                `class="${escapedClassName}"`,
                                `data-action-type="${escapedType}"`,
                                `data-ajax-method="${method}"`,
                                `data-ajax-url="${escapeHtml(href)}"`,
                                `data-ajax-token="${escapeHtml(token)}"`,
                                ...serializeHtmlAttributes(
                                    action.htmlAttributes,
                                    new Set([
                                        'type',
                                        'class',
                                        'data-action-type',
                                        'data-ajax-method',
                                        'data-ajax-url',
                                        'data-ajax-token',
                                        'data-confirm',
                                    ])
                                ),
                            ]

                            if (action.confirm) {
                                attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`)
                            }

                            return `<button ${attrs.join(' ')}>${iconHtml}${escapedLabel}</button>`
                        }

                        if (
                            action.type === 'DETAIL' ||
                            action.type === 'CUSTOM' ||
                            (action.type === 'EDIT' && href)
                        ) {
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
                            `type="button"`,
                            `class="${escapedClassName}"`,
                            `data-action-type="${escapedType}"`,
                            `data-id="${escapedId}"`,
                            ...serializeHtmlAttributes(
                                action.htmlAttributes,
                                new Set([
                                    'type',
                                    'class',
                                    'data-action-type',
                                    'data-id',
                                    'data-confirm',
                                ])
                            ),
                        ]

                        if (action.type === 'DELETE' && !mutationsEnabled) {
                            attrs.push('disabled', 'aria-disabled="true"')
                        }

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
}

export const actionColumnRenderer = createActionColumnRenderer()

function renderActionIcon(action: ActionConfig): string {
    if (action.lucideIcon) {
        const icon = renderLucideIcon(action.lucideIcon, {
            width: '1em',
            height: '1em',
            'aria-hidden': 'true',
        })

        return icon === null ? '' : `${icon} `
    }

    return action.icon ? `<i class="${escapeHtml(action.icon)}"></i> ` : ''
}

function isDeniedAction(action: ActionConfig, row: ActionRowData): boolean {
    if (action.denied === true) {
        return true
    }

    return row.__ux_datatables_denied_actions?.includes(action.name) ?? false
}

const DENIED_RESERVED_ATTRIBUTES = new Set([
    'type',
    'class',
    'data-action-type',
    'data-id',
    'data-confirm',
    'data-ajax-method',
    'data-ajax-url',
    'data-ajax-token',
    'href',
    'disabled',
    'aria-disabled',
])

/**
 * A denied action never carries a URL, a CSRF token, or a row id, so it renders as an inert button
 * whatever its type, links included.
 */
function renderDeniedAction(action: ActionConfig): string {
    const attrs = [
        `type="button"`,
        `class="${escapeHtml(`${action.className} disabled`.trim())}"`,
        `data-action-type="${escapeHtml(action.type)}"`,
        'disabled',
        'aria-disabled="true"',
        ...serializeHtmlAttributes(action.htmlAttributes, DENIED_RESERVED_ATTRIBUTES),
    ]

    return `<button ${attrs.join(' ')}>${renderActionIcon(action)}${escapeHtml(action.label)}</button>`
}

function resolveActionId(action: ActionConfig, row: ActionRowData): string | number | null {
    const idField = action.idField ?? 'id'
    const rowId = (row as Record<string, unknown>)[idField]

    if (isUsableActionId(rowId)) {
        return rowId
    }

    const resolvedId = row.__ux_datatables_actions?.[action.name]?.id

    return isUsableActionId(resolvedId) ? resolvedId : null
}

function isUsableActionId(value: unknown): value is string | number {
    if (typeof value === 'number') {
        return Number.isFinite(value)
    }

    return typeof value === 'string' && value.trim().length > 0
}

function resolveAjaxMethod(method: string): string | null {
    const normalized = method.toUpperCase()

    return normalized === 'POST' || normalized === 'DELETE' ? normalized : null
}

function resolveActionUrl(action: ActionConfig, row: ActionRowData): string | null {
    const resolvedUrl = row.__ux_datatables_actions?.[action.name]?.url

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
