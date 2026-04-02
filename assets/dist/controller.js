import { Controller } from '@hotwired/stimulus';
import { actionColumnRenderer } from './columnRenderers/actionColumnRenderer.js';
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js';
import { choiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js';
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js';
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js';
import { ApiPlatformAdapter } from './functions/apiPlatformAdapter.js';
import { deleteEntity } from './functions/deleteEntity.js';
import { detectStyleFramework } from './functions/detectStyleFramework.js';
import { createEditModal } from './functions/editModal.js';
import { ExtensionRegistry } from './functions/extensionRegistry.js';
import { fetchEditForm } from './functions/fetchEditForm.js';
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js';
import { submitEditForm } from './functions/submitEditForm.js';
import { toggleBooleanValue } from './functions/toggleBooleanValue.js';
const EXTENSION_MAP = {
    select: 'select',
    responsive: 'responsive',
    columnControl: 'columnControl',
    fixedColumns: 'fixedColumns',
    colReorder: 'colReorder',
    keys: 'keyTable',
    scroller: 'scroller',
};
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
        const framework = detectStyleFramework();
        const DataTable = await loadDataTableLibrary(framework);
        if (DataTable.isDataTable(this.element)) {
            this.isDataTableInitialized = true;
            return;
        }
        await this.loadExtensions(payload, framework, DataTable);
        if (this.isApiPlatformEnabled(payload)) {
            const columns = Array.isArray(payload.columns)
                ? payload.columns
                : [];
            new ApiPlatformAdapter(columns).configure(payload);
        }
        this.configureColumns(payload);
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        await this.initMercure(payload);
        this.bindActionHandler(payload);
        this.bindBooleanToggleHandler(payload);
        this.isDataTableInitialized = true;
    }
    disconnect() {
        this.eventSource?.close();
        this.eventSource = null;
    }
    async loadExtensions(payload, framework, DataTable) {
        if (payload?.layout?.topStart?.buttons) {
            const { loadButtonsLibrary } = await import('./functions/loadButtonsLibrary.js');
            await loadButtonsLibrary(DataTable, framework);
        }
        for (const [payloadKey, extensionName] of Object.entries(EXTENSION_MAP)) {
            if (payload?.[payloadKey]) {
                await ExtensionRegistry.load(extensionName, framework);
            }
        }
        if (payload?.select?.withCheckbox) {
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
    configureColumns(payload) {
        const columnRenderers = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl()),
            choiceColumnRenderer,
            emailColumnRenderer,
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
    }
    async initMercure(payload) {
        if (this.isMercureEnabled(payload)) {
            const { createMercureSubscription } = await import('./functions/mercureSubscription.js');
            this.eventSource = createMercureSubscription(payload.mercure, (event) => {
                this.dispatchEvent('mercure:message', { data: event.data, event });
                this.table?.ajax?.reload(null, false);
            });
        }
    }
    bindActionHandler(payload) {
        ;
        this.element.addEventListener('click', async (e) => {
            const target = e.target;
            const actionButton = target.closest('[data-action-type]');
            if (!actionButton) {
                return;
            }
            const actionType = actionButton.getAttribute('data-action-type');
            const entity = actionButton.getAttribute('data-entity');
            const id = actionButton.getAttribute('data-id');
            const confirmMessage = actionButton.getAttribute('data-confirm');
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
                return;
            }
            if (actionType === 'DELETE' && entity && id) {
                e.preventDefault();
                const response = await deleteEntity({
                    entity,
                    id,
                    topics: this.getMercureTopics(payload),
                });
                if (response.ok) {
                    this.table?.ajax?.reload(null, false);
                }
            }
            if (actionType === 'EDIT' && entity && id) {
                e.preventDefault();
                const columns = this.getEditableColumns(payload);
                const modal = await createEditModal();
                if (!modal)
                    return;
                const result = await fetchEditForm({ entity, id, columns });
                if (result.success) {
                    modal.show(result.html, async (formData) => {
                        const submitResult = await submitEditForm({
                            entity,
                            id,
                            columns,
                            formData,
                            topics: this.getMercureTopics(payload),
                        });
                        if (submitResult.success) {
                            modal.hide();
                            this.table?.ajax?.reload(null, false);
                        }
                        else if (submitResult.html) {
                            modal.showErrors(submitResult.html);
                        }
                    });
                }
            }
        });
    }
    getEditableColumns(payload) {
        if (!Array.isArray(payload.columns)) {
            return [];
        }
        return payload.columns
            .filter((column) => {
            if (Array.isArray(column.actions)) {
                return false;
            }
            const custom = column.customOptions ?? {};
            if (custom.hideWhenUpdating) {
                return false;
            }
            if (custom.templatePath || custom.routeName || custom.template) {
                return false;
            }
            return true;
        })
            .map((column) => ({
            name: column.name,
            title: column.title,
            type: column.type,
            field: column.field,
            customOptions: column.customOptions,
        }));
    }
    bindBooleanToggleHandler(payload) {
        this.element.addEventListener('change', async (e) => {
            const target = e.target;
            if (!(target instanceof HTMLInputElement) ||
                !target.matches('.boolean-switch-action')) {
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