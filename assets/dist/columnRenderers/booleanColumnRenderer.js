import { escapeHtml, parseBooleanValue } from '../functions/htmlUtils.js';
export function createBooleanColumnRenderer(toggleUrl, mutationsEnabled = true) {
    return {
        matches(column) {
            return true === column?.customOptions?.renderAsSwitch;
        },
        configure(column) {
            const customOptions = (column.customOptions ?? {});
            const defaultState = true === customOptions.defaultState;
            const toggleMethod = customOptions.toggleMethod ?? 'PATCH';
            const toggleIdField = customOptions.toggleIdField ?? 'id';
            const effectiveField = [customOptions.toggleField, column.field, column.data, column.name].find((field) => typeof field === 'string' && field.length > 0) ?? '';
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
                const rowId = row?.__ux_datatables_boolean_switches?.[effectiveField] ?? row?.[toggleIdField];
                const checked = boolValue ? ' checked' : '';
                const disabled = !mutationsEnabled || rowId === null || rowId === undefined || rowId === ''
                    ? ' disabled'
                    : '';
                const escapedId = escapeHtml(String(rowId ?? ''));
                const escapedUrl = escapeHtml(toggleUrl);
                const escapedField = escapeHtml(effectiveField);
                const escapedMethod = escapeHtml(toggleMethod.toUpperCase());
                return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-method="${escapedMethod}"${checked}${disabled}></div>`;
            };
        },
    };
}
//# sourceMappingURL=booleanColumnRenderer.js.map