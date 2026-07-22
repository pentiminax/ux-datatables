import { Controller } from '@hotwired/stimulus';
import { createActionColumnRenderer } from './columnRenderers/actionColumnRenderer.js';
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js';
import { createChoiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js';
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js';
import { createIconColumnRenderer, loadLucideIcons } from './columnRenderers/iconColumnRenderer.js';
import { imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js';
import { moneyColumnRenderer } from './columnRenderers/moneyColumnRenderer.js';
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js';
import { resolveColumnStyleAdapter } from './columnStyles/resolveColumnStyleAdapter.js';
import { ApiPlatformAdapter } from './functions/apiPlatformAdapter.js';
import { normalizeDisabledColumnControls } from './functions/columnControl.js';
import { deleteEntity } from './functions/deleteEntity.js';
import { detectStyleFramework } from './functions/detectStyleFramework.js';
import { ExtensionRegistry } from './functions/extensionRegistry.js';
import { fetchDetailRow } from './functions/fetchDetailRow.js';
import { fetchEditForm } from './functions/fetchEditForm.js';
import { registerFilterFeature } from './functions/filterFeature.js';
import { applyFilterLayout } from './functions/filterLayout.js';
import { FilterBar, hasFilters } from './functions/filters.js';
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js';
import { submitEditForm } from './functions/submitEditForm.js';
import { toggleBooleanValue } from './functions/toggleBooleanValue.js';
import { applyUrlStateToPayload, isUrlStateEnabled, readUrlState, writeUrlState, } from './functions/urlState.js';
import { resolveModalAdapter } from './modal/resolveModalAdapter.js';
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
        this.framework = 'dt';
        this.popstateHandler = null;
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
        this.framework = framework;
        const DataTable = await loadDataTableLibrary(framework);
        registerFilterFeature(DataTable);
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
        if (Array.isArray(payload.columns) &&
            payload.columns.some((column) => true === column?.customOptions?.isIcon)) {
            await loadLucideIcons();
        }
        const urlStateCfg = isUrlStateEnabled(payload);
        if (urlStateCfg) {
            applyUrlStateToPayload(payload, readUrlState(urlStateCfg));
        }
        if (hasFilters(payload)) {
            const filterBar = new FilterBar(payload, framework);
            filterBar.attachToPayload(payload);
            applyFilterLayout(payload, filterBar);
        }
        this.table = new DataTable(this.element, payload);
        this.dispatchEvent('connect', { table: this.table });
        if (urlStateCfg && this.table) {
            this.table.on('draw.dt', () => writeUrlState(urlStateCfg, this.table));
            this.popstateHandler = () => this.applyUrlStateToTable(urlStateCfg);
            window.addEventListener('popstate', this.popstateHandler);
        }
        await this.initMercure(payload);
        this.bindActionHandler(payload);
        this.bindBooleanToggleHandler(payload);
        this.isDataTableInitialized = true;
    }
    disconnect() {
        this.eventSource?.close();
        this.eventSource = null;
        if (this.popstateHandler) {
            window.removeEventListener('popstate', this.popstateHandler);
            this.popstateHandler = null;
        }
    }
    applyUrlStateToTable(cfg) {
        if (!this.table)
            return;
        const snap = readUrlState(cfg);
        if (snap.search !== undefined)
            this.table.search(snap.search);
        if (snap.order !== undefined)
            this.table.order(snap.order);
        if (snap.pageLength !== undefined)
            this.table.page.len(snap.pageLength);
        if (snap.start !== undefined) {
            const pageLen = this.table.page.len();
            this.table.page(Math.floor(snap.start / (pageLen || 10)));
        }
        this.table.draw(false);
    }
    async loadExtensions(payload, framework, DataTable) {
        if (this.hasButtonsInLayout(payload)) {
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
                ...(payload.columnDefs ?? []),
            ];
        }
    }
    configureColumns(payload) {
        normalizeDisabledColumnControls(payload);
        const style = resolveColumnStyleAdapter(this.framework);
        const columnRenderers = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl(), this.areMutationsEnabled(payload) &&
                typeof payload.dataTable === 'string' &&
                payload.dataTable.length > 0, style),
            createChoiceColumnRenderer(style),
            emailColumnRenderer,
            moneyColumnRenderer,
            imageColumnRenderer,
            urlColumnRenderer,
            createIconColumnRenderer(style),
            createActionColumnRenderer(this.areMutationsEnabled(payload)),
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
            if (actionType === 'DETAIL' && entity && id) {
                e.preventDefault();
                const rowElement = actionButton.closest('tr');
                const row = rowElement ? this.table?.row(rowElement) : null;
                if (!row) {
                    return;
                }
                if (row.child.isShown()) {
                    row.child.hide();
                    actionButton.classList.remove('expanded');
                    return;
                }
                const result = await fetchDetailRow({
                    entity,
                    id,
                    dataTableClass: payload.dataTableClass ?? null,
                });
                if (result.success) {
                    row.child(result.html).show();
                    actionButton.classList.add('expanded');
                }
            }
            if (actionType === 'DELETE' && entity && id) {
                e.preventDefault();
                const response = await deleteEntity({
                    entity,
                    id,
                    dataTableClass: payload.dataTableClass ?? null,
                    csrfToken: this.getCsrfToken(payload),
                });
                if (response.ok) {
                    this.table?.ajax?.reload(null, false);
                }
            }
            if (actionType === 'EDIT' && entity && id) {
                e.preventDefault();
                const modalConfig = payload.editModal ?? {};
                const modal = await resolveModalAdapter(modalConfig.adapter ?? null, this.framework);
                if (!modal)
                    return;
                const result = await fetchEditForm({
                    entity,
                    id,
                    dataTableClass: payload.dataTableClass ?? null,
                });
                if (result.success) {
                    await modal.show(result.html, {
                        onSubmit: async (formData) => {
                            const submitResult = await submitEditForm({
                                entity,
                                id,
                                formData,
                                dataTableClass: payload.dataTableClass ?? null,
                            });
                            if (submitResult.success) {
                                await modal.hide();
                                this.table?.ajax?.reload(null, false);
                            }
                            else if (submitResult.html) {
                                modal.replaceBody(submitResult.html);
                            }
                        },
                    });
                }
            }
        });
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
            const method = target.dataset.method ?? 'PATCH';
            const dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : '';
            if (!id || !field) {
                target.checked = !target.checked;
                console.error('Missing ID or field for boolean switch update');
                return;
            }
            if (!dataTable) {
                target.checked = !target.checked;
                console.error('Missing DataTable token for boolean toggle endpoint');
                return;
            }
            const previousState = !target.checked;
            target.disabled = true;
            try {
                const response = await toggleBooleanValue({
                    url: url ?? this.getBooleanToggleUrl(),
                    id,
                    field,
                    newValue: target.checked,
                    method,
                    dataTable,
                    csrfToken: this.getCsrfToken(payload),
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
    hasButtonsInLayout(payload) {
        const layout = payload?.layout;
        if (!layout)
            return false;
        return Object.values(layout).some((value) => {
            if (value === 'buttons')
                return true;
            if (typeof value === 'object' && value !== null) {
                if (Array.isArray(value)) {
                    return value.some((v) => v === 'buttons' ||
                        (typeof v === 'object' && v !== null && 'buttons' in v));
                }
                if ('buttons' in value)
                    return true;
            }
            return false;
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
    getCsrfToken(payload) {
        const token = payload?.csrfToken;
        return typeof token === 'string' && token.length > 0 ? token : undefined;
    }
    areMutationsEnabled(payload) {
        return payload?.mutationsEnabled === true;
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