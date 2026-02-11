import {Controller} from '@hotwired/stimulus';
import {getLoadedDataTablesStyleSheet} from "./functions/getLoadedDataTablesStyleSheet";
import {loadButtonsLibrary} from "./functions/loadButtonsLibrary";
import {loadDataTableLibrary} from "./functions/loadDataTableLibrary";
import {loadSelectLibrary} from "./functions/loadSelectLibrary";
import {loadResponsiveLibrary} from "./functions/loadResponsiveLibrary";
import {loadColReorderLibrary} from './functions/loadColReorderLibrary';
import {loadColumnControlLibrary} from "./functions/loadColumnControlLibrary";
import {loadFixedColumnsLibrary} from './functions/loadFixedColumnsLibrary';
import {loadKeyTableLibrary} from "./functions/loadKeyTableLibrary";
import {loadScrollerLibrary} from "./functions/loadScrollerLibrary";
import {deleteRow} from "./functions/delete";
import {toggleBooleanValue} from "./functions/toggleBoolean";

export default class extends Controller {
    declare readonly viewValue: any;

    static readonly values = {
        view: Object,
    };

    private table: DataTable | null = null;
    private isDataTableInitialized = false;

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
                        targets: 0
                    }
                ]
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

        payload.columns.forEach((column: any): void => {
            if (this.isBooleanColumn(column)) {
                this.configureBooleanColumnRender(column);
            }

            if (column.action === 'DELETE') {
                column.render = function (data: any, type: string, row: any) {
                    const className = `${column.action.toLowerCase()}-action`;

                    return `<button class="btn btn-danger ${className}" data-id="${row.id}" data-url="${column.actionUrl}">${column.actionLabel}</button>`;
                };
            }
        });

        this.table = new DataTable(this.element as HTMLElement, payload);

        this.dispatchEvent('connect', {table: this.table});

        this.element.addEventListener('click', async (e: MouseEvent): Promise<void> => {
            const target = e.target as HTMLElement;

            if (target.matches('.delete-action')) {
                const url: string | null = target.getAttribute('data-url');
                const id: string | null = target.getAttribute('data-id');

                if (url && id) {
                    const response = await deleteRow({url, id});

                    if (response.ok) {
                        this.table.ajax.reload();
                    }
                } else {
                    console.error('Missing URL or ID for delete action');
                }
            }
        });

        this.element.addEventListener('change', async (e: Event): Promise<void> => {
            const target = e.target as EventTarget | null;
            if (!(target instanceof HTMLInputElement) || !target.matches('.boolean-switch-action')) {
                return;
            }

            const url = target.dataset.url;
            const id = target.dataset.id;
            const field = target.dataset.field;
            const entity = target.dataset.entity;
            const method = target.dataset.method ?? 'PATCH';

            if (!url || !id || !field || !entity) {
                target.checked = !target.checked;
                console.error('Missing URL, ID, entity or field for boolean switch update');

                return;
            }

            const previousState = !target.checked;

            target.disabled = true;

            try {
                const response = await toggleBooleanValue({
                    url,
                    id,
                    field,
                    entity,
                    value: target.checked,
                    method,
                });

                if (!response.ok) {
                    target.checked = previousState;
                    console.error(`Boolean switch update failed with status ${response.status}`);
                }
            } catch (error) {
                target.checked = previousState;
                console.error('Boolean switch update failed', error);
            } finally {
                target.disabled = false;
            }
        });

        this.isDataTableInitialized = true;
    }

    private dispatchEvent(name: string, payload: any) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables'
        });
    }

    private isButtonsExtensionEnabled(payload: Record<string, any>): boolean {
        return !!(payload?.layout?.topStart?.buttons);
    }

    private isSelectExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.select;
    }

    private isResponsiveExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.responsive;
    }

    private isColumnControlExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.columnControl;
    }

    private isFixedColumnsExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.fixedColumns;
    }
    
    private isColReorderExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.colReorder;
    }

    private isKeyTableExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.keys;
    }
      
    private isScrollerExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload?.scroller;
    }

    private isBooleanColumn(column: Record<string, any>): boolean {
        return true === column?.booleanRenderAsSwitch;
    }

    private configureBooleanColumnRender(column: Record<string, any>): void {
        const defaultState = true === column.booleanDefaultState;
        const toggleUrl = typeof column.booleanToggleUrl === 'string' ? column.booleanToggleUrl : '';
        const toggleMethod = typeof column.booleanToggleMethod === 'string' ? column.booleanToggleMethod : 'PATCH';
        const toggleIdField = typeof column.booleanToggleIdField === 'string' ? column.booleanToggleIdField : 'id';
        const toggleEntityClass = typeof column.booleanToggleEntityClass === 'string' ? column.booleanToggleEntityClass : '';

        column.type ??= 'num';
        column.render = (data: any, type: string, row: Record<string, any>): any => {
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
            const disabled = toggleUrl === '' || toggleEntityClass === '' ? ' disabled' : '';
            const escapedId = this.escapeHtml(String(rowId ?? ''));
            const escapedUrl = this.escapeHtml(toggleUrl);
            const escapedField = this.escapeHtml(column.data ?? column.name ?? '');
            const escapedMethod = this.escapeHtml(toggleMethod.toUpperCase());
            const escapedEntityClass = this.escapeHtml(toggleEntityClass);

            return `<div class="form-check form-switch m-0"><input class="form-check-input boolean-switch-action" type="checkbox" role="switch" aria-label="${boolValue ? 'ON' : 'OFF'}" data-id="${escapedId}" data-url="${escapedUrl}" data-field="${escapedField}" data-entity="${escapedEntityClass}" data-method="${escapedMethod}"${checked}${disabled}></div>`;
        };
    }

    private parseBooleanValue(value: any, defaultValue: boolean = false): boolean {
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

    private escapeHtml(value: string): string {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
