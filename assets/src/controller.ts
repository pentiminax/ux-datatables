import {Controller} from '@hotwired/stimulus';
import {getLoadedDataTablesStyleSheet} from "./functions/getLoadedDataTablesStyleSheet";
import {loadButtonsLibrary} from "./functions/loadButtonsLibrary";
import {loadDataTableLibrary} from "./functions/loadDataTableLibrary";
import {loadSelectLibrary} from "./functions/loadSelectLibrary";
import {loadResponsiveLibrary} from "./functions/loadResponsiveLibrary";
import {loadColumnControlLibrary} from "./functions/loadColumnControlLibrary";
import {performAction} from "./functions/performAction";

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

        if (this.isButtonsExtensionEnabled(payload)) {
            await loadButtonsLibrary(DataTable, stylesheet);
        }

        if (this.isSelectExtensionEnabled(payload)) {
            await loadSelectLibrary(stylesheet);
        }

        if (this.isResponsiveExtensionEnabled(payload)) {
            await loadResponsiveLibrary(stylesheet);
        }

        if (this.isColumnControlExtensionEnabled(payload)) {
            await loadColumnControlLibrary(stylesheet);
        }

        payload.columns.forEach((column: any): void => {
            if (column.actions) {
                column.render = (data: any, type: string, row: any) => {
                    let buttons = '';
                    column.actions.forEach((action: any) => {
                        const className = `${action.action.toLowerCase()}-action`;
                        buttons += `<button class="btn btn-primary ${className}" data-id="${row.id}" data-url="${action.url}" data-action="${action.action}">${action.label}</button>`;
                    });
                    return buttons;
                };
            }
        });

        this.table = new DataTable(this.element as HTMLElement, payload);

        this.dispatchEvent('connect', {table: this.table});

        this.element.addEventListener('click', async (e: MouseEvent): Promise<void> => {
            const target = e.target as HTMLElement;
            const action = target.getAttribute('data-action');

            if (action) {
                const url: string | null = target.getAttribute('data-url');
                const id: string | null = target.getAttribute('data-id');

                if (url && id) {
                    const response = await performAction({url, id, action});

                    if (response.ok) {
                        this.table.ajax.reload();
                    }
                } else {
                    console.error(`Missing URL or ID for ${action} action`);
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
}
