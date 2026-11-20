import { Controller } from '@hotwired/stimulus';
import { installBulkActionBar } from './bulk/BulkActionBar.js';
import { createActionColumnRenderer } from './columnRenderers/actionColumnRenderer.js';
import { createBooleanColumnRenderer } from './columnRenderers/booleanColumnRenderer.js';
import { createChoiceColumnRenderer } from './columnRenderers/choiceColumnRenderer.js';
import { emailColumnRenderer } from './columnRenderers/emailColumnRenderer.js';
import { createIconColumnRenderer } from './columnRenderers/iconColumnRenderer.js';
import { bindImagePlaceholder, imageColumnRenderer } from './columnRenderers/imageColumnRenderer.js';
import { moneyColumnRenderer } from './columnRenderers/moneyColumnRenderer.js';
import { relativeDateColumnRenderer } from './columnRenderers/relativeDateColumnRenderer.js';
import { textColumnRenderer } from './columnRenderers/textColumnRenderer.js';
import { isFormatted } from './columnRenderers/types.js';
import { urlColumnRenderer } from './columnRenderers/urlColumnRenderer.js';
import { resolveColumnStyleAdapter } from './columnStyles/resolveColumnStyleAdapter.js';
import { ApiPlatformAdapter, isApiPlatformAdapterEnabled, resolveColumnDataKey, } from './functions/apiPlatformAdapter.js';
import { applyCustomButtonActions } from './functions/applyCustomButtonActions.js';
import { normalizeDisabledColumnControls } from './functions/columnControl.js';
import { ensureSummaryFooter, hasSummaries, installColumnSummaries, } from './functions/columnSummaries.js';
import { detectTheme } from './functions/detectTheme.js';
import { installFilterBar } from './functions/filters.js';
import { isHighlightEnabled } from './functions/highlightUpdates.js';
import { isRecord } from './functions/htmlUtils.js';
import { isDataTableClone } from './functions/isDataTableClone.js';
import { loadDataTableLibrary } from './functions/loadDataTableLibrary.js';
import { hasLucideIcons, hasLucideIconsInActions, loadLucideIcons, } from './functions/lucideIcons.js';
import { packageLoaders } from './functions/packageLoaders.js';
import { ensurePolling } from './functions/polling.js';
import { installRequestParams } from './functions/requestParams.js';
import { restrictSearchBuilder } from './functions/restrictSearchBuilder.js';
import { applyTableLanguage, bindRowFeatures } from './functions/rowFeatures.js';
import { applyServerExportUrls } from './functions/serverExport.js';
import { shiftOrderForSelectColumn } from './functions/shiftOrderForSelectColumn.js';
import { applyThemeSearchPlaceholder } from './functions/themeSearchField.js';
import { applyUrlStateToPayload, isUrlStateEnabled, readUrlState, writeUrlState, } from './functions/urlState.js';
import { installRowActions } from './rowActions/RowActions.js';
import { isStyleFramework, registeredStyleFramework, } from './types/styleFramework.js';
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
    searchBuilder: 'searchBuilder',
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
        this.stopPolling = null;
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
                this.initPolling(this.viewValue);
                await this.startMercure(this.viewValue);
            }
            return;
        }
        const payload = this.viewValue;
        this.dispatchEvent('pre-connect', {
            config: payload,
        });
        const framework = isStyleFramework(payload.styleFramework)
            ? payload.styleFramework
            : registeredStyleFramework();
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
            apiPlatformAdapter = new ApiPlatformAdapter(payload.columns);
            apiPlatformAdapter.configure(payload);
        }
        this.configureColumns(payload);
        if (hasLucideIcons(payload.columns) ||
            hasLucideIconsInActions(payload.bulkActions?.actions) ||
            typeof payload.emptyState?.icon === 'string') {
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
        await applyTableLanguage(payload);
        applyServerExportUrls(payload, requestParams ? () => requestParams.current() : undefined);
        applyCustomButtonActions(payload);
        if (this.adoptLiveTable(DataTable)) {
            return;
        }
        const showSummaries = hasSummaries(payload) && ensureSummaryFooter(this.element, payload);
        this.table = new DataTable(this.element, payload);
        const themedContainer = this.element.closest('.dt-container');
        if (themedContainer && detectTheme() !== null) {
            applyThemeSearchPlaceholder(themedContainer);
        }
        if (showSummaries) {
            installColumnSummaries(this.table, payload);
        }
        this.dispatchEvent('connect', { table: this.table });
        if (urlStateCfg && this.table) {
            this.table.on('draw.dt', () => writeUrlState(urlStateCfg, this.table));
            this.bindPopstate(urlStateCfg);
        }
        bindImagePlaceholder(this.element);
        installRowActions(payload, {
            element: this.element,
            framework: this.framework,
            getTable: () => this.table,
            dispatch: (name, detail) => this.dispatchEvent(name, detail),
        });
        bindRowFeatures(payload, this.element, () => this.table, filterBar);
        this.isDataTableInitialized = true;
        this.initPolling(payload);
        await this.startMercure(payload);
    }
    disconnect() {
        document.removeEventListener('turbo:before-cache', this.onTurboBeforeCache);
        this.eventSource?.close();
        this.eventSource = null;
        this.stopPolling?.();
        this.stopPolling = null;
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
        this.initPolling(this.viewValue);
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
            await loadButtonsLibrary(DataTable, framework, payload.layout);
        }
        for (const [payloadKey, extensionName] of Object.entries(EXTENSION_MAP)) {
            if (payload?.[payloadKey]) {
                await packageLoaders.load(extensionName, framework);
            }
        }
        if (isRecord(payload.select) && payload.select.withCheckbox) {
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
                ...(Array.isArray(payload.columnDefs) ? payload.columnDefs : []),
            ];
        }
        restrictSearchBuilder(payload);
    }
    configureColumns(payload) {
        normalizeDisabledColumnControls(payload);
        const style = resolveColumnStyleAdapter(this.framework, detectTheme());
        const columnRenderers = [
            createBooleanColumnRenderer(payload.rowActionUrls?.toggle ?? '', this.areMutationsEnabled(payload) &&
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
            for (const renderer of isFormatted(column) ? [textColumnRenderer] : columnRenderers) {
                if (renderer.matches(column)) {
                    renderer.configure(column);
                }
            }
        });
        if (this.isApiPlatformEnabled(payload)) {
            payload.columns = payload.columns.map((column) => ({
                ...column,
                data: resolveColumnDataKey(column),
            }));
        }
    }
    async startMercure(payload) {
        try {
            await this.initMercure(payload);
        }
        catch (error) {
            console.error('UX DataTables: unable to start the Mercure subscription.', error);
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
    initPolling(payload) {
        this.stopPolling = ensurePolling(this.stopPolling, {
            table: this.table,
            payload,
            element: this.element,
            mercureEnabled: this.isMercureEnabled(payload),
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
        this.highlighter = new UpdateHighlighter(this.table, payload.highlight, payload.columns, (cells) => this.dispatchEvent('highlight', { cells }));
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
    isApiPlatformEnabled(payload) {
        return isApiPlatformAdapterEnabled(payload);
    }
    isMercureEnabled(payload) {
        return !!payload?.mercure?.hubUrl && this.getMercureTopics(payload).length > 0;
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
//# sourceMappingURL=coreController.js.map