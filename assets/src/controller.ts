import { Controller } from '@hotwired/stimulus';
import {getLoadedDataTablesStyleSheet} from "./functions/getLoadedDataTablesStyleSheet";
import {loadButtonsLibrary} from "./functions/loadButtonsLibrary";
import {loadDataTableLibrary} from "./functions/loadDataTableLibrary";
import {loadSelectLibrary} from "./functions/loadSelectLibrary";

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

        const DataTable = await loadDataTableLibrary(stylesheet);

        if (this.isButtonsExtensionEnabled(payload)) {
            await loadButtonsLibrary(DataTable, stylesheet);
        }

        if (this.isSelectExtensionEnabled(payload)) {
            await loadSelectLibrary(stylesheet);
        }

        this.table = new DataTable(this.element as HTMLElement, payload);

        this.dispatchEvent('connect', { table: this.table });

        this.isDataTableInitialized = true;
    }

    private dispatchEvent(name: string, payload: any) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables'
        });
    }

    private isButtonsExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload['layout']['buttons'];
    }

    private isSelectExtensionEnabled(payload: Record<string, any>): boolean {
        return !!payload['select'];
    }
}
