import { Controller } from '@hotwired/stimulus';
import { getLoadedDataTablesStyleSheet } from "./functions/getLoadedDataTablesStyleSheet.js";
import { loadButtonsLibrary } from "./functions/loadButtonsLibrary.js";
import { loadDataTableLibrary } from "./functions/loadDataTableLibrary.js";
import { loadSelectLibrary } from "./functions/loadSelectLibrary.js";
import { loadResponsiveLibrary } from "./functions/loadResponsiveLibrary.js";
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
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        this.isDataTableInitialized = true;
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
}
default_1.values = {
    view: Object,
};
export default default_1;
