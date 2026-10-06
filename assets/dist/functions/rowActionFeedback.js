const STATUS_ATTRIBUTE = 'data-ux-datatables-row-action-status';
const VISUALLY_HIDDEN = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0';
export async function runRowAction({ element, root, actionType, id, labels, reportSuccess = true, dispatch, run, }) {
    if (element.getAttribute('aria-busy') === 'true') {
        return null;
    }
    setBusy(element, true);
    try {
        const outcome = await run();
        if (outcome.ok) {
            if (reportSuccess) {
                dispatch('action:success', { actionType, id, response: outcome.response });
            }
            return outcome;
        }
        reportFailure(root, labels, dispatch, { actionType, id, response: outcome.response });
        return outcome;
    }
    catch (error) {
        reportFailure(root, labels, dispatch, { actionType, id, error });
    }
    finally {
        setBusy(element, false);
    }
    return null;
}
function reportFailure(root, labels, dispatch, detail) {
    const event = dispatch('action:error', detail);
    if (!event.defaultPrevented) {
        announce(root, failureMessage(detail.response?.status, labels));
    }
}
function failureMessage(status, labels) {
    return status === 403
        ? (labels.forbidden ?? 'You are not allowed to do this.')
        : (labels.failed ?? 'The action could not be completed.');
}
function announce(root, message) {
    const container = root.closest('.dt-container') ?? root.parentElement ?? root;
    let region = container.querySelector(`[${STATUS_ATTRIBUTE}]`);
    if (!region) {
        region = document.createElement('div');
        region.setAttribute(STATUS_ATTRIBUTE, '');
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('style', VISUALLY_HIDDEN);
        container.appendChild(region);
    }
    region.textContent = '';
    region.textContent = message;
}
function setBusy(element, busy) {
    if (busy) {
        element.setAttribute('aria-busy', 'true');
    }
    else {
        element.removeAttribute('aria-busy');
    }
    if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) {
        element.disabled = busy;
    }
}
//# sourceMappingURL=rowActionFeedback.js.map