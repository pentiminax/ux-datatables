import { escapeHtml, parseBooleanValue } from '../functions/htmlUtils.js';
export function createBooleanColumnRenderer(toggleUrl) {
    return {
        matches(column) {
            return true === column?.booleanRenderAsSwitch;
        },
        configure(column) {
            const defaultState = true === column.booleanDefaultState;
            const toggleMethod = typeof column.booleanToggleMethod === 'string' ? column.booleanToggleMethod : 'PATCH';
            const toggleIdField = typeof column.booleanToggleIdField === 'string' ? column.booleanToggleIdField : 'id';
            const toggleEntityClass = typeof column.booleanToggleEntityClass === 'string' ? column.booleanToggleEntityClass : '';
            column.type ??= 'num';
            column.render = (data, type, row) => {
                const boolValue = parseBooleanValue(data, defaultState);
                if (type === 'sort' || type === 'type') {
                    return boolValue ? 1 : 0;
                }
                if (type === 'filter') {
                    return boolValue ? 'ON' : 'OFF';
                }
                if (type !== 'display') {
                    return boolValue ? 'ON' : 'OFF';
                }
                const rowId = row?.[toggleIdField];
                const checked = boolValue ? ' checked' : '';
                const disabled = toggleEntityClass === '' ? ' disabled' : '';
                const escapedId = escapeHtml(String(rowId ?? ''));
                const escapedUrl = escapeHtml(toggleUrl);
                const escapedField = escapeHtml(column.booleanToggleField ?? column.data ?? column.name ?? '');
                const escapedMethod = escapeHtml(toggleMethod.toUpperCase());
                const escapedEntityClass = escapeHtml(toggleEntityClass);
                return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-entity="${escapedEntityClass}" data-method="${escapedMethod}"${checked}${disabled}></div>`;
            };
        },
    };
}
//# sourceMappingURL=booleanColumnRenderer.js.map