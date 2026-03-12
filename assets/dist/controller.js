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
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js';
import { choiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js';
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js';
import { actionColumnRenderer } from './columnRenderers/actionColumnRenderer.js';
import { deleteEntity } from './functions/deleteEntity.js';
class default_1 extends Controller {
    constructor() {
        super(...arguments);
        this.table = null;
        this.isDataTableInitialized = false;
        this.eventSource = null;
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
        const columnRenderers = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl()),
            choiceColumnRenderer,
            urlColumnRenderer,
            actionColumnRenderer,
        ];
        payload.columns.forEach((column) => {
            for (const renderer of columnRenderers) {
                if (renderer.matches(column)) {
                    renderer.configure(column);
                }
            }
        });
        if (this.isApiPlatformEnabled(payload) && Array.isArray(payload.columns)) {
            payload.columns = payload.columns.map((column) => ({
                ...column,
                data: column.field ?? column.data,
            }));
        }
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        if (this.isMercureEnabled(payload)) {
            const { createMercureSubscription } = await import('./functions/mercureSubscription.js');
            this.eventSource = createMercureSubscription(payload.mercure, (event) => {
                this.dispatchEvent('mercure:message', { data: event.data, event });
                this.table?.ajax?.reload(null, false);
            });
        }
        this.element.addEventListener('click', async (e) => {
            const target = e.target;
            const actionButton = target.closest('[data-action-type]');
            if (actionButton) {
                const actionType = actionButton.getAttribute('data-action-type');
                const entity = actionButton.getAttribute('data-entity');
                const id = actionButton.getAttribute('data-id');
                const confirmMessage = actionButton.getAttribute('data-confirm');
                if (confirmMessage && !confirm(confirmMessage)) {
                    return;
                }
                if (actionType === 'DELETE' && entity && id) {
                    const response = await deleteEntity({
                        entity,
                        id,
                        topics: this.getMercureTopics(payload),
                    });
                    if (response.ok) {
                        this.table?.ajax?.reload();
                    }
                }
                return;
            }
            if (target.matches('.delete-action')) {
                console.warn('UX DataTables: .delete-action is deprecated. Use configureActions() instead.');
                const url = target.getAttribute('data-url');
                const id = target.getAttribute('data-id');
                if (url && id) {
                    const response = await deleteRow({ url, id });
                    if (response.ok) {
                        this.table?.ajax?.reload();
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
                    topics: this.getMercureTopics(payload),
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
    disconnect() {
        this.eventSource?.close();
        this.eventSource = null;
    }
    dispatchEvent(name, payload) {
        this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        });
    }
    getBooleanToggleUrl() {
        return '/datatables/ajax/edit';
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
    isMercureEnabled(payload) {
        return !!payload?.mercure?.hubUrl && this.getMercureTopics(payload).length > 0;
    }
    getMercureTopics(payload) {
        const topics = payload?.mercure?.topics;
        if (Array.isArray(topics) && topics.length > 0) {
            return topics.filter((topic) => typeof topic === 'string' && topic.length > 0);
        }
        return [];
    }
}
default_1.values = {
    view: Object,
};
export default default_1;
//# sourceMappingURL=controller.js.map