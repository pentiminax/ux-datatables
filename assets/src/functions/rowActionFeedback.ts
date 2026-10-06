export interface RowActionLabels {
    failed?: string
    forbidden?: string
}

export interface RowActionOutcome {
    ok: boolean
    response?: Response
}

export interface RunRowActionOptions {
    element: HTMLElement
    root: HTMLElement
    actionType: string
    id: string
    labels: RowActionLabels
    reportSuccess?: boolean
    dispatch: (name: string, detail: Record<string, unknown>) => Event
    run: () => Promise<RowActionOutcome>
}

const STATUS_ATTRIBUTE = 'data-ux-datatables-row-action-status'
const VISUALLY_HIDDEN =
    'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0'

/**
 * Runs a built-in row action with its feedback: one request at a time per control, a cancelable
 * `action:success` / `action:error` event shared with custom Ajax actions, and an accessible
 * message when nothing cancels the error event. Resolves to whether the action succeeded.
 */
export async function runRowAction({
    element,
    root,
    actionType,
    id,
    labels,
    reportSuccess = true,
    dispatch,
    run,
}: RunRowActionOptions): Promise<boolean> {
    if (element.getAttribute('aria-busy') === 'true') {
        return false
    }

    setBusy(element, true)

    try {
        const outcome = await run()

        if (outcome.ok) {
            if (reportSuccess) {
                dispatch('action:success', { actionType, id, response: outcome.response })
            }

            return true
        }

        reportFailure(root, labels, dispatch, { actionType, id, response: outcome.response })
    } catch (error) {
        reportFailure(root, labels, dispatch, { actionType, id, error })
    } finally {
        setBusy(element, false)
    }

    return false
}

function reportFailure(
    root: HTMLElement,
    labels: RowActionLabels,
    dispatch: RunRowActionOptions['dispatch'],
    detail: { actionType: string; id: string; response?: Response; error?: unknown }
): void {
    const event = dispatch('action:error', detail)

    if (!event.defaultPrevented) {
        announce(root, failureMessage(detail.response?.status, labels))
    }
}

function failureMessage(status: number | undefined, labels: RowActionLabels): string {
    return status === 403
        ? (labels.forbidden ?? 'You are not allowed to do this.')
        : (labels.failed ?? 'The action could not be completed.')
}

function announce(root: HTMLElement, message: string): void {
    const container = root.closest('.dt-container') ?? root.parentElement ?? root
    let region = container.querySelector<HTMLElement>(`[${STATUS_ATTRIBUTE}]`)

    if (!region) {
        region = document.createElement('div')
        region.setAttribute(STATUS_ATTRIBUTE, '')
        region.setAttribute('aria-live', 'polite')
        region.setAttribute('style', VISUALLY_HIDDEN)
        container.appendChild(region)
    }

    // Emptying first makes a screen reader read a repeated message again.
    region.textContent = ''
    region.textContent = message
}

function setBusy(element: HTMLElement, busy: boolean): void {
    if (busy) {
        element.setAttribute('aria-busy', 'true')
    } else {
        element.removeAttribute('aria-busy')
    }

    if (element instanceof HTMLButtonElement || element instanceof HTMLInputElement) {
        element.disabled = busy
    }
}
