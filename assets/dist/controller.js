import {Controller} from '@hotwired/stimulus';
import {getLoadedDataTablesStyleSheet} from "./functions/getLoadedDataTablesStyleSheet.js";
import {loadButtonsLibrary} from "./functions/loadButtonsLibrary.js";
import {loadDataTableLibrary} from "./functions/loadDataTableLibrary.js";
import {loadSelectLibrary} from "./functions/loadSelectLibrary.js";
import {loadResponsiveLibrary} from "./functions/loadResponsiveLibrary.js";
import {loadColumnControlLibrary} from "./functions/loadColumnControlLibrary.js";
import {deleteRow} from "./functions/deleteRow.js";

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

        payload.columns.forEach((column, index) => {
            if (column.action === 'DELETE') {
                column.render = function (data, type, row) {
                    const className = `${column.action.toLowerCase()}-action`;

                    return `<button class="btn btn-danger ${className}" data-id="${row.id}" data-url="${column.actionUrl}">${column.actionLabel}</button>`;
                };
            }
        });

        this.table = new DataTable(this.element, payload);
        this.isDataTableInitialized = true;

        this.element.addEventListener('click', async (e) => {
            if (e.target.matches('.delete-action')) {
                const url = e.target.getAttribute('data-url');

                if (url) {
                    const response = await deleteRow({
                        id: e.target.getAttribute('data-id'),
                        url: url,
                    });

                    if (response.ok) {
                        this.table.ajax.reload();
                    }
                }
            }
        });

        this.dispatchEvent('connect', {table: this.table});
    }

    dispatchEvent(name, payload) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables'
        });
    }

    isButtonsExtensionEnabled(payload) {
        return !!(payload?.layout?.topStart?.buttons);
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
}

default_1.values = {
    view: Object,
};

export default default_1;