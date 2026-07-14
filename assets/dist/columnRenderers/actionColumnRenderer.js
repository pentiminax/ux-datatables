import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js';
const SAFE_ATTRIBUTE_NAME_PATTERN = /^[a-zA-Z_:][a-zA-Z0-9:._-]*$/;
const DEFAULT_COLLAPSIBLE_ICON = '<span class="dtr-control-icon" aria-hidden="true">&#9656;</span> ';
export function createActionColumnRenderer(mutationsEnabled = true) {
    return {
        matches(column) {
            return Array.isArray(column?.actions);
        },
        configure(column) {
            const actions = column.actions;
            column.render = (data, type, row) => {
                if (type !== 'display') {
                    return '';
                }
                return actions
                    .filter((action) => {
                    if (!action.displayCondition) {
                        return true;
                    }
                    const { field, value } = action.displayCondition;
                    return row[field] === value;
                })
                    .map((action) => {
                    const id = resolveActionId(action, row);
                    const escapedId = escapeHtml(String(id ?? ''));
                    const escapedEntity = escapeHtml(action.entityClass ?? '');
                    const escapedLabel = escapeHtml(action.label);
                    const escapedClassName = escapeHtml(action.className);
                    const escapedType = escapeHtml(action.type);
                    const iconHtml = action.icon
                        ? `<i class="${escapeHtml(action.icon)}"></i> `
                        : '';
                    if (action.type === 'DETAIL' && action.collapsible) {
                        const iconMarkup = iconHtml || DEFAULT_COLLAPSIBLE_ICON;
                        const attrs = [
                            `type="button"`,
                            `class="${escapedClassName}"`,
                            `data-action-type="${escapedType}"`,
                            `data-entity="${escapedEntity}"`,
                            `data-id="${escapedId}"`,
                            ...serializeHtmlAttributes(action.htmlAttributes, new Set([
                                'type',
                                'class',
                                'data-action-type',
                                'data-entity',
                                'data-id',
                            ])),
                        ];
                        return `<button ${attrs.join(' ')}>${iconMarkup}${escapedLabel}</button>`;
                    }
                    if (action.type === 'DETAIL' || action.type === 'CUSTOM') {
                        const href = resolveActionUrl(action, row);
                        if (!href || isUnsafeUrl(href)) {
                            return '';
                        }
                        const attrs = [
                            `class="${escapedClassName}"`,
                            `href="${escapeHtml(href)}"`,
                            `data-action-type="${escapedType}"`,
                            ...serializeHtmlAttributes(action.htmlAttributes, new Set(['class', 'href', 'data-action-type', 'data-confirm'])),
                        ];
                        if (action.confirm) {
                            attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`);
                        }
                        return `<a ${attrs.join(' ')}>${iconHtml}${escapedLabel}</a>`;
                    }
                    const attrs = [
                        `class="${escapedClassName}"`,
                        `data-action-type="${escapedType}"`,
                        `data-entity="${escapedEntity}"`,
                        `data-id="${escapedId}"`,
                        ...serializeHtmlAttributes(action.htmlAttributes, new Set([
                            'class',
                            'data-action-type',
                            'data-entity',
                            'data-id',
                            'data-confirm',
                        ])),
                    ];
                    if (action.type === 'DELETE' && !mutationsEnabled) {
                        attrs.push('disabled', 'aria-disabled="true"');
                    }
                    if (action.confirm) {
                        attrs.push(`data-confirm="${escapeHtml(action.confirm)}"`);
                    }
                    return `<button ${attrs.join(' ')}>${iconHtml}${escapedLabel}</button>`;
                })
                    .filter(Boolean)
                    .join(' ');
            };
        },
    };
}
export const actionColumnRenderer = createActionColumnRenderer();
function resolveActionId(action, row) {
    const idField = action.idField ?? 'id';
    const rowId = row[idField];
    if (isUsableActionId(rowId)) {
        return rowId;
    }
    const resolvedId = row.__ux_datatables_actions?.[action.name]?.id;
    return isUsableActionId(resolvedId) ? resolvedId : null;
}
function isUsableActionId(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value);
    }
    return typeof value === 'string' && value.trim().length > 0;
}
function resolveActionUrl(action, row) {
    const resolvedUrl = row.__ux_datatables_actions?.[action.name]?.url;
    if (typeof resolvedUrl === 'string' && resolvedUrl.trim().length > 0) {
        return resolvedUrl;
    }
    if (typeof action.url === 'string' && action.url.trim().length > 0) {
        return action.url;
    }
    return null;
}
function serializeHtmlAttributes(htmlAttributes, reservedAttributes) {
    if (!htmlAttributes) {
        return [];
    }
    return Object.entries(htmlAttributes).flatMap(([name, value]) => {
        const normalizedName = name.toLowerCase();
        if (!SAFE_ATTRIBUTE_NAME_PATTERN.test(name) || reservedAttributes.has(normalizedName)) {
            return [];
        }
        if (typeof value === 'boolean') {
            return value ? [name] : [];
        }
        if (null === value || undefined === value) {
            return [];
        }
        return [`${name}="${escapeHtml(String(value))}"`];
    });
}
//# sourceMappingURL=actionColumnRenderer.js.map