import { confirmAction } from '../bulk/confirmModal.js';
import { deleteEntity } from '../functions/deleteEntity.js';
import { fetchDetailRow } from '../functions/fetchDetailRow.js';
import { fetchEditForm } from '../functions/fetchEditForm.js';
import { forwardedQuery } from '../functions/forwardedQuery.js';
import { runRowAction } from '../functions/rowActionFeedback.js';
import { rowActionUrl } from '../functions/rowActionUrl.js';
import { runAjaxAction } from '../functions/runAjaxAction.js';
import { submitEditForm } from '../functions/submitEditForm.js';
import { toggleBooleanValue } from '../functions/toggleBooleanValue.js';
import { resolveModalAdapter } from '../modal/resolveModalAdapter.js';
export function installRowActions(payload, context) {
    new RowActions(payload, context).bind();
}
class RowActions {
    constructor(payload, context) {
        this.payload = payload;
        this.confirmingActions = new WeakSet();
        this.element = context.element;
        this.framework = context.framework;
        this.getTable = context.getTable;
        this.dispatch = context.dispatch;
    }
    bind() {
        this.bindActionHandler(this.payload);
        this.bindBooleanToggleHandler(this.payload);
    }
    csrfToken(payload) {
        const token = payload?.csrfToken;
        return typeof token === 'string' && token.length > 0 ? token : undefined;
    }
    bindActionHandler(payload) {
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
                if (this.confirmingActions.has(actionButton)) {
                    return;
                }
                this.confirmingActions.add(actionButton);
                let confirmed;
                try {
                    confirmed = handledHere
                        ? await confirmAction({
                            message: confirmMessage,
                            confirmLabel: payload.actionLabels?.confirm ?? 'Confirm',
                            cancelLabel: payload.actionLabels?.cancel ?? 'Cancel',
                            framework: this.framework,
                            adapterKey: payload.editModal?.adapter ?? null,
                        })
                        : confirm(confirmMessage);
                }
                finally {
                    this.confirmingActions.delete(actionButton);
                }
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
                const row = rowElement ? this.getTable()?.row(rowElement) : null;
                if (!row) {
                    return;
                }
                if (row.child.isShown()) {
                    row.child.hide();
                    actionButton.classList.remove('expanded');
                    return;
                }
                await this.runRowAction(actionButton, 'DETAIL', id, payload, async () => {
                    const result = await fetchDetailRow({
                        url: rowActionUrl(payload, 'detail'),
                        dataTable,
                        id,
                        query: forwardedQuery(payload),
                    });
                    if (result.success) {
                        row.child(result.html).show();
                        actionButton.classList.add('expanded');
                    }
                    return { ok: result.success, response: result.response };
                });
            }
            if (actionType === 'DELETE' && dataTable && id) {
                e.preventDefault();
                const deleted = await this.runRowAction(actionButton, 'DELETE', id, payload, async () => {
                    const response = await deleteEntity({
                        url: rowActionUrl(payload, 'delete'),
                        dataTable,
                        id,
                        csrfToken: this.csrfToken(payload),
                        query: forwardedQuery(payload),
                    });
                    return { ok: response.ok, response };
                });
                if (deleted?.ok) {
                    this.getTable()?.ajax?.reload(null, false);
                }
            }
            if (actionType === 'EDIT' && dataTable && id) {
                e.preventDefault();
                const modalConfig = payload.editModal ?? {};
                const modal = await resolveModalAdapter(modalConfig.adapter ?? null, this.framework);
                if (!modal)
                    return;
                const loaded = await this.runRowAction(actionButton, 'EDIT', id, payload, async () => {
                    const result = await fetchEditForm({
                        url: rowActionUrl(payload, 'editForm'),
                        dataTable,
                        id,
                        query: forwardedQuery(payload),
                    });
                    return { ok: result.success, ...result };
                }, false);
                if (loaded?.ok) {
                    await modal.show(loaded.html, {
                        onSubmit: async (formData) => {
                            const saved = await this.runRowAction(actionButton, 'EDIT', id, payload, async () => {
                                const result = await submitEditForm({
                                    url: rowActionUrl(payload, 'editFormSubmit'),
                                    dataTable,
                                    id,
                                    formData,
                                    csrfToken: this.csrfToken(payload),
                                    query: forwardedQuery(payload),
                                });
                                return { ok: result.success, ...result };
                            });
                            if (saved?.ok) {
                                await modal.hide();
                                this.getTable()?.ajax?.reload(null, false);
                            }
                            else if (saved?.html) {
                                modal.replaceBody(saved.html);
                            }
                        },
                    });
                }
            }
        });
    }
    runRowAction(element, actionType, id, payload, run, reportSuccess = true) {
        return runRowAction({
            reportSuccess,
            element,
            root: this.element,
            actionType,
            id,
            labels: payload.actionLabels ?? {},
            dispatch: (name, detail) => this.dispatch(name, detail),
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
            dispatch: (name, detail) => this.dispatch(name, detail),
            navigate: (target) => window.location.assign(target),
            reload: () => {
                if (payload.ajax) {
                    this.getTable()?.ajax?.reload(null, false);
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
            const rowId = target.dataset.rowId;
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
                    url: url || rowActionUrl(payload, 'toggle'),
                    id,
                    rowId,
                    field,
                    newValue: target.checked,
                    method,
                    dataTable,
                    csrfToken: this.csrfToken(payload),
                    query: forwardedQuery(payload),
                });
                return { ok: response.ok, response };
            });
            if (!toggled?.ok) {
                target.checked = previousState;
            }
        });
    }
}
//# sourceMappingURL=RowActions.js.map