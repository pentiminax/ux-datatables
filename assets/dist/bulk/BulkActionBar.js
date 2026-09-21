import { runBulkAction } from '../functions/runBulkAction.js';
import { confirmBulkAction } from './confirmModal.js';
import { SelectionStore } from './selectionStore.js';
const BOOTSTRAP_FRAMEWORKS = ['bs', 'bs4', 'bs5'];
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
        this.store = null;
        this.api = null;
        this.running = false;
        this.config = getBulkActionsConfig(payload);
        this.labels = this.config.labels ?? {};
        this.dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : '';
        this.csrfToken =
            typeof payload.csrfToken === 'string' && payload.csrfToken.length > 0
                ? payload.csrfToken
                : undefined;
        this.mutationsEnabled = payload.mutationsEnabled === true;
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'dt-bulk-bar';
        this.wrapper.hidden = true;
        this.wrapper.setAttribute('role', 'toolbar');
        this.wrapper.setAttribute('aria-label', this.labels.selected ?? 'Selected rows');
        this.counter = document.createElement('span');
        this.counter.className = 'dt-bulk-bar__count';
        this.selectAllButton = this.createButton(this.labels.selectAllMatching ?? 'Select all matching', 'dt-bulk-bar__select-all');
        this.selectAllButton.hidden = true;
        this.clearButton = this.createButton(this.labels.clear ?? 'Clear', 'dt-bulk-bar__clear');
        this.actionsWrapper = document.createElement('div');
        this.actionsWrapper.className = 'dt-bulk-bar__actions';
        this.status = document.createElement('span');
        this.status.className = 'dt-bulk-bar__status';
        this.status.setAttribute('role', 'status');
        this.wrapper.append(this.counter, this.selectAllButton, this.clearButton, this.actionsWrapper, this.status);
    }
    render(api) {
        this.api = api;
        this.store = new SelectionStore(api);
        for (const action of this.config.actions) {
            this.actionsWrapper.appendChild(this.createActionButton(action));
        }
        this.selectAllButton.addEventListener('click', () => this.store?.selectAllMatching());
        this.clearButton.addEventListener('click', () => this.store?.clear());
        this.store.attach((snapshot) => this.update(snapshot));
        return this.wrapper;
    }
    update(snapshot) {
        this.wrapper.hidden = snapshot.count === 0;
        this.counter.textContent = (this.labels.selected ?? '{count} selected').replace('{count}', String(snapshot.count));
        this.selectAllButton.hidden =
            this.config.selectCurrentPageOnly === true ||
                snapshot.allMatching ||
                snapshot.count === 0 ||
                snapshot.count >= snapshot.totalCount;
        if (snapshot.allMatching) {
            this.status.textContent = (this.labels.allMatchingSelected ?? '{count} rows selected across every page').replace('{count}', String(snapshot.count));
        }
    }
    createActionButton(action) {
        const button = this.createButton(action.label, action.className ?? 'dt-bulk-bar__action', action.icon);
        button.dataset.bulkAction = action.name;
        if (action.denied === true || !this.mutationsEnabled || !this.config.url) {
            button.disabled = true;
            return button;
        }
        button.addEventListener('click', () => void this.execute(action, button));
        return button;
    }
    async execute(action, button) {
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
            });
            if (!confirmed) {
                return;
            }
        }
        this.running = true;
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
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
            this.status.textContent = this.summarize(action, result.processed, result.skipped);
            this.dispatch(result.success ? 'bulk:success' : 'bulk:error', {
                action: action.name,
                result,
            });
            if (result.success && action.deselectAfterCompletion !== false) {
                this.store.clear();
            }
            this.reload();
        }
        catch (error) {
            this.dispatch('bulk:error', { action: action.name, error });
        }
        finally {
            this.running = false;
            button.removeAttribute('aria-busy');
            button.disabled = false;
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
        button.className = this.buttonClass(className);
        if (icon) {
            const iconElement = document.createElement('i');
            iconElement.className = icon;
            button.appendChild(iconElement);
        }
        button.appendChild(document.createTextNode(label));
        return button;
    }
    buttonClass(className) {
        return BOOTSTRAP_FRAMEWORKS.includes(this.framework)
            ? `btn btn-sm btn-outline-secondary ${className}`
            : `dt-button ${className}`;
    }
}
//# sourceMappingURL=BulkActionBar.js.map