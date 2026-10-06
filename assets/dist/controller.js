import { Controller } from '@hotwired/stimulus';
import { installBulkActionBar } from './bulk/BulkActionBar.js';
import { confirmAction } from './bulk/confirmModal.js';
import { createActionColumnRenderer } from './columnRenderers/actionColumnRenderer.js';
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js';
import { createChoiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js';
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js';
import { createIconColumnRenderer } from './columnRenderers/iconColumnRenderer.js';
import { imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js';
import { moneyColumnRenderer } from './columnRenderers/moneyColumnRenderer.js';
import { relativeDateColumnRenderer } from './columnRenderers/relativeDateColumnRenderer.js';
import { textColumnRenderer } from './columnRenderers/textColumnRenderer.js';
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js';
import { resolveColumnStyleAdapter } from './columnStyles/resolveColumnStyleAdapter.js';
import { ApiPlatformAdapter, isApiPlatformAdapterEnabled, resolveColumnDataKey, } from './functions/apiPlatformAdapter.js';
import { applyCustomButtonActions } from './functions/applyCustomButtonActions.js';
import { normalizeDisabledColumnControls } from './functions/columnControl.js';
import { deleteEntity } from './functions/deleteEntity.js';
import { detectStyleFramework } from './functions/detectStyleFramework.js';
import { detectTheme } from './functions/detectTheme.js';
import { ExtensionRegistry } from './functions/extensionRegistry.js';
import { fetchDetailRow } from './functions/fetchDetailRow.js';
import { fetchEditForm } from './functions/fetchEditForm.js';
import { installFilterBar } from './functions/filters.js';
import { isHighlightEnabled } from './functions/highlightUpdates.js';
import { isDataTableClone } from './functions/isDataTableClone.js';
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js';
import { applyLocalLanguage } from './functions/localLanguage.js';
import { hasLucideIcons, hasLucideIconsInActions, loadLucideIcons, } from './functions/lucideIcons.js';
import { installRequestParams } from './functions/requestParams.js';
import { runAjaxAction } from './functions/runAjaxAction.js';
import { runRowAction } from './functions/rowActionFeedback.js';
import { applyServerExportUrls } from './functions/serverExport.js';
import { shiftOrderForSelectColumn } from './functions/shiftOrderForSelectColumn.js';
import { submitEditForm } from './functions/submitEditForm.js';
import { applyThemeSearchPlaceholder } from './functions/themeSearchField.js';
import { toggleBooleanValue } from './functions/toggleBooleanValue.js';
import { applyUrlStateToPayload, isUrlStateEnabled, readUrlState, writeUrlState, } from './functions/urlState.js';
import { resolveModalAdapter } from './modal/resolveModalAdapter.js';
import { isStyleFramework } from './types/styleFramework.js';
const EXTENSION_MAP = {
    select: 'select',
    responsive: 'responsive',
    columnControl: 'columnControl',
    fixedColumns: 'fixedColumns',
    fixedHeader: 'fixedHeader',
    colReorder: 'colReorder',
    keys: 'keyTable',
    rowGroup: 'rowGroup',
    scroller: 'scroller',
};
const GENERATED_MARKUP_SELECTOR = [
    '.dt-layout-row',
    '.dt-layout-cell',
    '.dt-layout-start',
    '.dt-layout-end',
    '.dt-layout-full',
    '.dt-length',
    '.dt-search',
    '.dt-info',
    '.dt-paging',
    '.dt-processing',
    '.dt-scroll',
    '.dt-buttons',
    'table.dataTable',
].join(',');
class default_1 extends Controller {
    constructor() {
        super(...arguments);
        this.table = null;
        this.isDataTableInitialized = false;
        this.eventSource = null;
        this.highlighter = null;
        this.framework = 'dt';
        this.popstateHandler = null;
        this.onTurboBeforeCache = () => {
            this.table?.destroy();
            this.table = null;
        };
    }
    async connect() {
        if (!(this.element instanceof HTMLTableElement)) {
            throw new Error('Invalid element');
        }
        if (isDataTableClone(this.element)) {
            return;
        }
        document.addEventListener('turbo:before-cache', this.onTurboBeforeCache);
        if (this.isDataTableInitialized) {
            if (this.table) {
                this.dispatchEvent('reconnect', { table: this.table });
                this.bindPopstate(isUrlStateEnabled(this.viewValue));
                await this.initMercure(this.viewValue);
            }
            return;
        }
        const payload = this.viewValue;
        this.dispatchEvent('pre-connect', {
            config: payload,
        });
        const framework = isStyleFramework(payload.styleFramework)
            ? payload.styleFramework
            : detectStyleFramework();
        this.framework = framework;
        const DataTable = await loadDataTableLibrary(framework);
        if (this.adoptLiveTable(DataTable)) {
            return;
        }
        this.resetRestoredMarkup();
        await this.loadExtensions(payload, framework, DataTable);
        this.dispatchEvent('pre-init', { config: payload, DataTable });
        let apiPlatformAdapter = null;
        if (this.isApiPlatformEnabled(payload)) {
            const columns = Array.isArray(payload.columns)
                ? payload.columns
                : [];
            apiPlatformAdapter = new ApiPlatformAdapter(columns);
            apiPlatformAdapter.configure(payload);
        }
        this.configureColumns(payload);
        if (hasLucideIcons(payload.columns) ||
            hasLucideIconsInActions(payload.bulkActions?.actions)) {
            await loadLucideIcons();
        }
        const urlStateCfg = isUrlStateEnabled(payload);
        if (urlStateCfg) {
            applyUrlStateToPayload(payload, readUrlState(urlStateCfg));
        }
        let requestParams = null;
        const filterBar = installFilterBar(payload, DataTable, framework);
        const bulkBar = installBulkActionBar(payload, DataTable, framework, (name, detail) => this.dispatchEvent(name, detail), () => requestParams?.current() ?? {});
        if (filterBar || bulkBar || apiPlatformAdapter) {
            requestParams = installRequestParams(payload, {
                filters: filterBar ? () => filterBar.collectValues() : undefined,
                transport: apiPlatformAdapter
                    ? (params) => apiPlatformAdapter.toRequestParams(params)
                    : undefined,
            });
        }
        await applyLocalLanguage(payload);
        applyServerExportUrls(payload);
        applyCustomButtonActions(payload);
        if (this.adoptLiveTable(DataTable)) {
            return;
        }
        this.table = new DataTable(this.element, payload);
        const themedContainer = this.element.closest('.dt-container');
        if (themedContainer && detectTheme() !== null) {
            applyThemeSearchPlaceholder(themedContainer);
        }
        this.dispatchEvent('connect', { table: this.table });
        if (urlStateCfg && this.table) {
            this.table.on('draw.dt', () => writeUrlState(urlStateCfg, this.table));
            this.bindPopstate(urlStateCfg);
        }
        await this.initMercure(payload);
        this.bindActionHandler(payload);
        this.bindBooleanToggleHandler(payload);
        this.isDataTableInitialized = true;
    }
    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onTurboBeforeCache);
        this.eventSource?.close();
        this.eventSource = null;
        this.highlighter?.destroy();
        this.highlighter = null;
        if (this.popstateHandler) {
            window.removeEventListener('popstate', this.popstateHandler);
            this.popstateHandler = null;
        }
    }
    adoptLiveTable(DataTable) {
        if (!DataTable.isDataTable(this.element)) {
            return false;
        }
        this.isDataTableInitialized = true;
        this.table = new DataTable.Api(this.element);
        this.dispatchEvent('reconnect', { table: this.table });
        this.bindPopstate(isUrlStateEnabled(this.viewValue));
        return true;
    }
    bindPopstate(cfg) {
        if (!cfg || this.popstateHandler || !this.element.isConnected) {
            return;
        }
        this.popstateHandler = () => this.applyUrlStateToTable(cfg);
        window.addEventListener('popstate', this.popstateHandler);
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
    resetRestoredMarkup() {
        const element = this.element;
        if (!element.classList.contains('dataTable') && element.childElementCount === 0) {
            return;
        }
        const container = this.findGeneratedWrapper(element);
        if (container) {
            for (const child of Array.from(container.children)) {
                if (!child.contains(element) && this.isGeneratedMarkup(child, element.id)) {
                    child.remove();
                }
            }
        }
        element.replaceChildren();
        element.classList.remove('dataTable');
    }
    findGeneratedWrapper(element) {
        if (!element.id) {
            return null;
        }
        const container = element.closest('.dt-container');
        return container?.id === `${element.id}_wrapper` ? container : null;
    }
    isGeneratedMarkup(node, tableId) {
        if (node.matches(GENERATED_MARKUP_SELECTOR) ||
            node.querySelector(GENERATED_MARKUP_SELECTOR)) {
            return true;
        }
        const prefix = `${tableId}_`;
        return (node.id.startsWith(prefix) ||
            Array.from(node.querySelectorAll('[id]')).some((el) => el.id.startsWith(prefix)));
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
            payload.order = shiftOrderForSelectColumn(payload.order);
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
        const style = resolveColumnStyleAdapter(this.framework, detectTheme());
        const columnRenderers = [
            createBooleanColumnRenderer(this.getBooleanToggleUrl(), this.areMutationsEnabled(payload) &&
                typeof payload.dataTable === 'string' &&
                payload.dataTable.length > 0, style),
            createChoiceColumnRenderer(style),
            emailColumnRenderer,
            moneyColumnRenderer,
            relativeDateColumnRenderer,
            imageColumnRenderer,
            urlColumnRenderer,
            createIconColumnRenderer(style),
            createActionColumnRenderer(this.areMutationsEnabled(payload)),
            textColumnRenderer,
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
                data: resolveColumnDataKey(column),
            }));
        }
    }
    async initMercure(payload) {
        if (this.eventSource || !this.isMercureEnabled(payload)) {
            return;
        }
        await this.initHighlighter(payload);
        const { createMercureSubscription } = await import('./functions/mercureSubscription.js');
        if (!this.element.isConnected || this.eventSource) {
            return;
        }
        this.eventSource = createMercureSubscription(payload.mercure, (event) => {
            this.dispatchEvent('mercure:message', { data: event.data, event });
            this.highlighter?.arm();
            this.table?.ajax?.reload(() => this.highlighter?.diff(), false);
        });
    }
    async initHighlighter(payload) {
        if (!this.table || !isHighlightEnabled(payload)) {
            return;
        }
        const { UpdateHighlighter } = await import('./functions/highlightUpdates.js');
        if (!this.element.isConnected || this.highlighter) {
            return;
        }
        this.highlighter = new UpdateHighlighter(this.table, payload.highlight, Array.isArray(payload.columns) ? payload.columns : [], (cells) => this.dispatchEvent('highlight', { cells }));
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
            const id = actionButton.getAttribute('data-id');
            const dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : '';
            const confirmMessage = actionButton.getAttribute('data-confirm');
            const ajaxMethod = actionButton.getAttribute('data-ajax-method');
            const handledHere = !!ajaxMethod ||
                (['DETAIL', 'DELETE', 'EDIT'].includes(actionType ?? '') && !!dataTable && !!id);
            if (confirmMessage) {
                if (handledHere) {
                    e.preventDefault();
                }
                const confirmed = handledHere
                    ? await confirmAction({
                        message: confirmMessage,
                        confirmLabel: payload.actionLabels?.confirm ?? 'Confirm',
                        cancelLabel: payload.actionLabels?.cancel ?? 'Cancel',
                        framework: this.framework,
                        adapterKey: payload.editModal?.adapter ?? null,
                    })
                    : confirm(confirmMessage);
                if (!confirmed) {
                    e.preventDefault();
                    return;
                }
            }
            if (ajaxMethod) {
                e.preventDefault();
                await this.executeAjaxAction(actionButton, ajaxMethod, payload);
                return;
            }
            if (actionType === 'DETAIL' && dataTable && id) {
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
                await this.runRowAction(actionButton, 'DETAIL', id, payload, async () => {
                    const result = await fetchDetailRow({ dataTable, id });
                    if (result.success) {
                        row.child(result.html).show();
                        actionButton.classList.add('expanded');
                    }
                    return { ok: result.success };
                });
            }
            if (actionType === 'DELETE' && dataTable && id) {
                e.preventDefault();
                const deleted = await this.runRowAction(actionButton, 'DELETE', id, payload, async () => {
                    const response = await deleteEntity({
                        dataTable,
                        id,
                        csrfToken: this.getCsrfToken(payload),
                    });
                    return { ok: response.ok, response };
                });
                if (deleted) {
                    this.table?.ajax?.reload(null, false);
                }
            }
            if (actionType === 'EDIT' && dataTable && id) {
                e.preventDefault();
                const modalConfig = payload.editModal ?? {};
                const modal = await resolveModalAdapter(modalConfig.adapter ?? null, this.framework);
                if (!modal)
                    return;
                let formHtml = '';
                const loaded = await this.runRowAction(actionButton, 'EDIT', id, payload, async () => {
                    const result = await fetchEditForm({ dataTable, id });
                    formHtml = result.html;
                    return { ok: result.success };
                });
                if (loaded) {
                    await modal.show(formHtml, {
                        onSubmit: async (formData) => {
                            const submitResult = await submitEditForm({
                                dataTable,
                                id,
                                formData,
                                csrfToken: this.getCsrfToken(payload),
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
    runRowAction(element, actionType, id, payload, run) {
        return runRowAction({
            element,
            root: this.element,
            actionType,
            id,
            labels: payload.actionLabels ?? {},
            dispatch: (name, detail) => this.dispatchEvent(name, detail),
            run,
        });
    }
    async executeAjaxAction(button, method, payload) {
        const url = button.getAttribute('data-ajax-url');
        const token = button.getAttribute('data-ajax-token');
        if (!url || !token) {
            return;
        }
        await runAjaxAction({
            button,
            method,
            url,
            token,
            dispatch: (name, detail) => this.dispatchEvent(name, detail),
            navigate: (target) => window.location.assign(target),
            reload: () => {
                if (payload.ajax) {
                    this.table?.ajax?.reload(null, false);
                    return;
                }
                window.location.reload();
            },
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
            const toggled = await this.runRowAction(target, 'TOGGLE', id, payload, async () => {
                const response = await toggleBooleanValue({
                    url: url ?? this.getBooleanToggleUrl(),
                    id,
                    field,
                    newValue: target.checked,
                    method,
                    dataTable,
                    csrfToken: this.getCsrfToken(payload),
                });
                return { ok: response.ok, response };
            });
            if (!toggled) {
                target.checked = previousState;
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
        return this.dispatch(name, {
            detail: payload,
            prefix: 'datatables',
        });
    }
    getBooleanToggleUrl() {
        return '/datatables/ajax/edit';
    }
    isApiPlatformEnabled(payload) {
        return isApiPlatformAdapterEnabled(payload);
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