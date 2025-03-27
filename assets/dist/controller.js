import { Controller } from '@hotwired/stimulus';
import { getLoadedDataTablesStyleSheet } from "./functions/getLoadedDataTablesStyleSheet.js";
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
        const DataTable = await this.loadDataTableLibrary(stylesheet);
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        this.isDataTableInitialized = true;
    }
    async loadDataTableLibrary(stylesheet) {
        if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
            return (await import('datatables.net-bs5')).default;
        }
        else {
            return (await import('datatables.net-dt')).default;
        }
    }
    dispatchEvent(name, payload) {
        this.dispatch(name, { detail: payload, prefix: 'datatables' });
    }
}
default_1.values = {
    view: Object,
};
export default default_1;
