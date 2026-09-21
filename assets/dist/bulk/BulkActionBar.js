import { renderLucideIcon } from '../functions/lucideIcons.js';
import { createPopover } from '../functions/popover.js';
import { runBulkAction } from '../functions/runBulkAction.js';
import { confirmBulkAction } from './confirmModal.js';
import { SelectionStore } from './selectionStore.js';
const BOOTSTRAP_FRAMEWORKS = ['bs', 'bs4', 'bs5'];
const KEBAB_ICON = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="dt-bulk-trigger__icon">' +
    '<circle cx="10" cy="4" r="1.6" /><circle cx="10" cy="10" r="1.6" />' +
    '<circle cx="10" cy="16" r="1.6" /></svg>';
export function hasBulkActions(payload) {
    const config = payload?.bulkActions;
    return (config !== null &&
        typeof config === 'object' &&
        Array.isArray(config.actions) &&
        config.actions.length > 0);
}
export function getBulkActionsConfig(payload) {
    return payload.bulkActions;
}
export class BulkActionBar {
    constructor(payload, framework, dispatch = () => { }) {
        this.framework = framework;
        this.dispatch = dispatch;
        this.popover = null;
        this.store = null;
        this.api = null;
        this.running = false;
        this.resultMessage = null;
        this.config = getBulkActionsConfig(payload);
        this.labels = this.config.labels ?? {};
        this.dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : '';
        this.csrfToken =
            typeof payload.csrfToken === 'string' && payload.csrfToken.length > 0
                ? payload.csrfToken
                : undefined;
        this.mutationsEnabled = payload.mutationsEnabled === true;
        this.modalAdapterKey =
            typeof payload.editModal?.adapter === 'string' ? payload.editModal.adapter : null;
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'dt-bulk';
        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = this.buttonClass('dt-bulk-trigger');
        this.trigger.disabled = true;
        this.trigger.setAttribute('aria-haspopup', 'menu');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.trigger.innerHTML = KEBAB_ICON;
        this.trigger.appendChild(document.createTextNode(this.labels.trigger ?? 'Bulk actions'));
        this.menu = document.createElement('div');
        this.menu.className = 'dt-bulk-menu';
        this.menu.setAttribute('role', 'menu');
        this.menu.hidden = true;
        this.wrapper.append(this.trigger, this.menu);
        this.summary = document.createElement('div');
        this.summary.className = 'dt-layout-row dt-layout-full dt-bulk-summary';
        this.setSummaryEmpty(true);
        this.summaryCount = document.createElement('span');
        this.summaryCount.className = 'dt-bulk-summary__count';
        this.summaryCount.setAttribute('role', 'status');
        this.selectAllButton = this.createLink(this.labels.selectAllMatching ?? 'Select all {count}', 'dt-bulk-summary__select-all');
        this.selectAllButton.hidden = true;
        this.clearButton = this.createLink(this.labels.clear ?? 'Deselect all', 'dt-bulk-summary__clear');
        const summaryActions = document.createElement('div');
        summaryActions.className = 'dt-bulk-summary__actions';
        summaryActions.append(this.selectAllButton, this.clearButton);
        this.summary.append(this.summaryCount, summaryActions);
    }
    render(api) {
        this.api = api;
        this.store = new SelectionStore(api);
        for (const action of this.config.actions) {
            this.menu.appendChild(this.createMenuItem(action));
        }
        this.popover = createPopover({
            wrapper: this.wrapper,
            panel: this.menu,
            toggle: this.trigger,
        });
        this.trigger.addEventListener('click', () => this.popover?.toggle());
        this.selectAllButton.addEventListener('click', () => this.store?.selectAllMatching());
        this.clearButton.addEventListener('click', () => this.store?.clear());
        this.store.attach((snapshot) => this.update(snapshot));
        queueMicrotask(() => this.mountSummary());
        return this.wrapper;
    }
    mountSummary() {
        if (this.summary.isConnected) {
            return;
        }
        const container = this.wrapper.closest('.dt-container') ??
            this.api?.table?.().container?.();
        const tableRow = container?.querySelector('.dt-layout-table');
        tableRow?.parentNode?.insertBefore(this.summary, tableRow);
    }
    update(snapshot) {
        this.mountSummary();
        this.trigger.disabled = snapshot.count === 0 || !this.canRun();
        if (snapshot.count === 0) {
            this.popover?.close();
        }
        this.setSummaryEmpty(snapshot.count === 0 && this.resultMessage === null);
        this.summaryCount.textContent = this.resultMessage ?? this.countLabel(snapshot);
        this.selectAllButton.hidden =
            this.config.selectCurrentPageOnly === true ||
                snapshot.allMatching ||
                snapshot.count === 0 ||
                snapshot.count >= snapshot.totalCount;
        this.selectAllButton.textContent = (this.labels.selectAllMatching ?? 'Select all {count}').replace('{count}', String(snapshot.totalCount));
        this.clearButton.hidden = snapshot.count === 0;
    }
    setSummaryEmpty(empty) {
        this.summary.classList.toggle('dt-bulk-summary--empty', empty);
    }
    countLabel(snapshot) {
        const template = snapshot.allMatching
            ? (this.labels.allMatchingSelected ?? '{count} records selected across every page')
            : (this.labels.selected ?? '{count} records selected');
        return template.replace('{count}', String(snapshot.count));
    }
    canRun() {
        return this.mutationsEnabled && !!this.config.url;
    }
    createMenuItem(action) {
        const item = this.createButton(action.label, `dt-bulk-menu__item ${action.className ?? ''}`.trim(), action);
        item.dataset.bulkAction = action.name;
        item.setAttribute('role', 'menuitem');
        if (action.denied === true || !this.canRun()) {
            item.disabled = true;
            return item;
        }
        item.addEventListener('click', () => {
            this.popover?.close();
            void this.execute(action);
        });
        return item;
    }
    async execute(action) {
        if (this.running || !this.store || !this.config.url) {
            return;
        }
        const snapshot = this.store.snapshot();
        if (snapshot.count === 0) {
            return;
        }
        if (action.confirm) {
            const confirmed = await confirmBulkAction({
                message: action.confirm.replace('{count}', String(snapshot.count)),
                confirmLabel: action.confirmButton ?? this.labels.confirm ?? 'Confirm',
                cancelLabel: this.labels.cancel ?? 'Cancel',
                framework: this.framework,
                adapterKey: this.modalAdapterKey,
            });
            if (!confirmed) {
                return;
            }
        }
        this.running = true;
        this.trigger.setAttribute('aria-busy', 'true');
        this.trigger.disabled = true;
        this.dispatch('bulk:start', { action: action.name, selection: snapshot });
        try {
            const result = await runBulkAction({
                url: this.config.url,
                dataTable: this.dataTable,
                action: action.name,
                ids: snapshot.ids,
                allMatching: snapshot.allMatching,
                deselectedIds: snapshot.deselectedIds,
                query: snapshot.allMatching ? this.currentQuery() : {},
                csrfToken: this.csrfToken,
            });
            this.resultMessage = this.summarize(action, result.processed, result.skipped);
            this.dispatch(result.success ? 'bulk:success' : 'bulk:error', {
                action: action.name,
                result,
            });
            if (result.success && action.deselectAfterCompletion !== false) {
                this.store.clear();
            }
            this.setSummaryEmpty(false);
            this.summaryCount.textContent = this.resultMessage;
            this.reload();
        }
        catch (error) {
            this.dispatch('bulk:error', { action: action.name, error });
        }
        finally {
            this.running = false;
            this.trigger.removeAttribute('aria-busy');
            this.trigger.disabled = this.store.snapshot().count === 0 || !this.canRun();
        }
    }
    summarize(action, processed, skipped) {
        const parts = [
            action.successMessage ??
                (this.labels.processed ?? '{count} rows processed').replace('{count}', String(processed)),
        ];
        if (skipped > 0) {
            parts.push((this.labels.skipped ?? '{count} rows skipped').replace('{count}', String(skipped)));
        }
        return parts.join(' ');
    }
    currentQuery() {
        const params = this.api?.ajax?.params?.();
        return params !== null && typeof params === 'object' ? params : {};
    }
    reload() {
        this.api?.ajax?.reload?.(null, false);
    }
    createButton(label, className, icon) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        const lucide = icon?.lucideIcon
            ? renderLucideIcon(icon.lucideIcon, {
                width: '1em',
                height: '1em',
                'aria-hidden': 'true',
            })
            : null;
        if (null !== lucide) {
            button.insertAdjacentHTML('afterbegin', lucide);
        }
        else if (icon?.icon) {
            const iconElement = document.createElement('i');
            iconElement.className = icon.icon;
            button.appendChild(iconElement);
        }
        button.appendChild(document.createTextNode(label));
        return button;
    }
    createLink(label, className) {
        return this.createButton(label, `dt-bulk-summary__link ${className}`);
    }
    buttonClass(className) {
        return BOOTSTRAP_FRAMEWORKS.includes(this.framework)
            ? `btn btn-sm btn-outline-secondary ${className}`
            : `dt-button ${className}`;
    }
}
//# sourceMappingURL=BulkActionBar.js.map