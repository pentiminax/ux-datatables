import { escapeHtml } from '../functions/htmlUtils.js';
export const actionColumnRenderer = {
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
                const idField = action.idField ?? 'id';
                const escapedId = escapeHtml(String(row[idField] ?? ''));
                const escapedEntity = escapeHtml(action.entityClass ?? '');
                const escapedLabel = escapeHtml(action.label);
                const escapedCssClass = escapeHtml(action.cssClass);
                const escapedType = escapeHtml(action.type);
                let attrs = `class="${escapedCssClass}" data-action-type="${escapedType}" data-entity="${escapedEntity}" data-id="${escapedId}"`;
                if (action.confirmationButtonLabel) {
                    attrs += ` data-confirm="${escapeHtml(action.confirmationButtonLabel)}"`;
                }
                const iconHtml = action.icon ? `<i class="${escapeHtml(action.icon)}"></i> ` : '';
                return `<button ${attrs}>${iconHtml}${escapedLabel}</button>`;
            })
                .join(' ');
        };
    },
};
//# sourceMappingURL=actionColumnRenderer.js.map