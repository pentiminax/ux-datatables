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
}
