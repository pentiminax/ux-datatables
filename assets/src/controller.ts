import { Controller } from '@hotwired/stimulus';
import {getLoadedDataTablesStyleSheet} from "./functions/getLoadedDataTablesStyleSheet";

export default class extends Controller {
    declare readonly viewValue: any;

    static values = {
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

        const DataTable = await this.loadDataTableLibrary(stylesheet);

        this.table = new DataTable(this.element as HTMLElement, payload);

        this.dispatchEvent('connect', { table: this.table });

        this.isDataTableInitialized = true;
    }

    async loadDataTableLibrary(stylesheet?: CSSStyleSheet) {
        if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
            return (await import('datatables.net-bs5')).default;
        } else {
            return (await import('datatables.net-dt')).default;
        }
    }

    private dispatchEvent(name: string, payload: any) {
        this.dispatch(name, { detail: payload, prefix: 'datatables' });
    }
}
