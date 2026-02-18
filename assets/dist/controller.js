import { Controller } from '@hotwired/stimulus';
import { getLoadedDataTablesStyleSheet } from './functions/getLoadedDataTablesStyleSheet.js';
import { loadButtonsLibrary } from './functions/loadButtonsLibrary.js';
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js';
import { loadSelectLibrary } from './functions/loadSelectLibrary.js';
import { loadResponsiveLibrary } from './functions/loadResponsiveLibrary.js';
import { loadColReorderLibrary } from './functions/loadColReorderLibrary.js';
import { loadColumnControlLibrary } from './functions/loadColumnControlLibrary.js';
import { loadFixedColumnsLibrary } from './functions/loadFixedColumnsLibrary.js';
import { loadKeyTableLibrary } from './functions/loadKeyTableLibrary.js';
import { loadScrollerLibrary } from './functions/loadScrollerLibrary.js';
import { deleteRow } from './functions/deleteRow.js';
import { toggleBooleanValue } from './functions/toggleBooleanValue.js';
import { ApiPlatformAdapter } from './functions/apiPlatformAdapter.js';
class default_1 extends Controller {
    constructor() {
        super(...arguments);
        this.table = null;
        this.isDataTableInitialized = false;
    }
    async connect() {
        if (this.isDataTableInitialized) {
            return;
        }
        if (!(this.element instanceof HTMLTableElement)) {
            throw new Error('Invalid element');
        }
        const payload = this.viewValue;
        this.dispatchEvent('pre-connect', {
            config: payload,
        });
        const stylesheet = getLoadedDataTablesStyleSheet();
        const DataTable = await loadDataTableLibrary(stylesheet);
        if (DataTable.isDataTable(this.element)) {
            this.isDataTableInitialized = true;
            return;
        }
        if (this.isButtonsExtensionEnabled(payload)) {
            await loadButtonsLibrary(DataTable, stylesheet);
        }
        if (this.isSelectExtensionEnabled(payload)) {
            await loadSelectLibrary(stylesheet);
        }
        if (this.isResponsiveExtensionEnabled(payload)) {
            await loadResponsiveLibrary(stylesheet);
            if (payload.select?.withCheckbox) {
                payload.columns.unshift({
                    data: null,
                    defaultContent: '',
                    name: null,
                    orderable: false,
                    searchable: false,
                    title: '',
                });
                payload.columnDefs = [
                    {
                        orderable: false,
                        render: DataTable.render.select(),
                        targets: 0,
                    },
                ];
            }
        }
        if (this.isColumnControlExtensionEnabled(payload)) {
            await loadColumnControlLibrary(stylesheet);
        }
        if (this.isFixedColumnsExtensionEnabled(payload)) {
            await loadFixedColumnsLibrary(stylesheet);
        }
        if (this.isColReorderExtensionEnabled(payload)) {
            await loadColReorderLibrary(stylesheet);
        }
        if (this.isKeyTableExtensionEnabled(payload)) {
            await loadKeyTableLibrary(stylesheet);
        }
        if (this.isScrollerExtensionEnabled(payload)) {
            await loadScrollerLibrary(stylesheet);
        }
        if (this.isApiPlatformEnabled(payload)) {
            const columns = Array.isArray(payload.columns) ? payload.columns : [];
            new ApiPlatformAdapter(columns).configure(payload);
        }
        payload.columns.forEach((column) => {
            if (this.isBooleanColumn(column)) {
                this.configureBooleanColumnRender(column);
            }
            if (column.action === 'DELETE') {
                column.render = function (data, type, row) {
                    const className = `${column.action.toLowerCase()}-action`;
                    return `<button class="btn btn-danger ${className}" data-id="${row.id}" data-url="${column.actionUrl}">${column.actionLabel}</button>`;
                };
            }
        });
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        this.element.addEventListener('click', async (e) => {
            const target = e.target;
            if (target.matches('.delete-action')) {
                const url = target.getAttribute('data-url');
                const id = target.getAttribute('data-id');
                if (url && id) {
                    const response = await deleteRow({ url, id });
                    if (response.ok) {
                        this.table.ajax.reload();
                    }
                }
                else {
                    console.error('Missing URL or ID for delete action');
                }
            }
        });
        this.element.addEventListener('change', async (e) => {
            const target = e.target;
            if (!(target instanceof HTMLInputElement) || !target.matches('.boolean-switch-action')) {
                return;
            }
            const url = target.dataset.url;
            const id = target.dataset.id;
            const field = target.dataset.field;
            const entity = target.dataset.entity;
            const method = target.dataset.method ?? 'PATCH';
            if (!id || !field) {
                target.checked = !target.checked;
                console.error('Missing ID or field for boolean switch update');
                return;
            }
            if (!entity) {
                target.checked = !target.checked;
                console.error('Missing entity for boolean toggle endpoint');
                return;
            }
            const previousState = !target.checked;
            target.disabled = true;
            try {
                const response = await toggleBooleanValue({
                    url: url ?? this.getBooleanToggleUrl(),
                    id,
                    field,
                    entity,
                    newValue: target.checked,
                    method,
                });
                if (!response.ok) {
                    target.checked = previousState;
                    console.error(`Boolean switch update failed with status ${response.status}`);
                }
            }
            catch (error) {
                target.checked = previousState;
                console.error('Boolean switch update failed', error);
            }
            finally {
                target.disabled = false;
            }
        });
        this.isDataTableInitialized = true;
    }
    dispatchEvent(name, payload) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        });
    }
    isButtonsExtensionEnabled(payload) {
        return !!payload?.layout?.topStart?.buttons;
    }
    isSelectExtensionEnabled(payload) {
        return !!payload?.select;
    }
    isResponsiveExtensionEnabled(payload) {
        return !!payload?.responsive;
    }
    isColumnControlExtensionEnabled(payload) {
        return !!payload?.columnControl;
    }
    isFixedColumnsExtensionEnabled(payload) {
        return !!payload?.fixedColumns;
    }
    isColReorderExtensionEnabled(payload) {
        return !!payload?.colReorder;
    }
    isKeyTableExtensionEnabled(payload) {
        return !!payload?.keys;
    }
    isScrollerExtensionEnabled(payload) {
        return !!payload?.scroller;
    }
    isApiPlatformEnabled(payload) {
        return true === payload?.apiPlatform;
    }
    isBooleanColumn(column) {
        return true === column?.booleanRenderAsSwitch;
    }
    configureBooleanColumnRender(column) {
        const defaultState = true === column.booleanDefaultState;
        const toggleUrl = this.getBooleanToggleUrl();
        const toggleMethod = typeof column.booleanToggleMethod === 'string' ? column.booleanToggleMethod : 'PATCH';
        const toggleIdField = typeof column.booleanToggleIdField === 'string' ? column.booleanToggleIdField : 'id';
        const toggleEntityClass = typeof column.booleanToggleEntityClass === 'string' ? column.booleanToggleEntityClass : '';
        column.type ??= 'num';
        column.render = (data, type, row) => {
            const boolValue = this.parseBooleanValue(data, defaultState);
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
            const escapedId = this.escapeHtml(String(rowId ?? ''));
            const escapedUrl = this.escapeHtml(toggleUrl);
            const escapedField = this.escapeHtml(column.booleanToggleField ?? column.data ?? column.name ?? '');
            const escapedMethod = this.escapeHtml(toggleMethod.toUpperCase());
            const escapedEntityClass = this.escapeHtml(toggleEntityClass);
            return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-entity="${escapedEntityClass}" data-method="${escapedMethod}"${checked}${disabled}></div>`;
        };
    }
    getBooleanToggleUrl() {
        return '/datatables/ajax/edit';
    }
    parseBooleanValue(value, defaultValue = false) {
        if (null === value || undefined === value || '' === value) {
            return defaultValue;
        }
        if (typeof value === 'boolean') {
            return value;
        }
        if (typeof value === 'number') {
            return value !== 0;
        }
        if (typeof value === 'string') {
            const normalized = value.trim().toLowerCase();
            return ['1', 'true', 'yes', 'y', 'on'].includes(normalized);
        }
        return false;
    }
    escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
default_1.values = {
    view: Object,
};
export default default_1;
//# sourceMappingURL=controller.js.map