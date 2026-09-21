import { renderLucideIcon } from '../functions/lucideIcons.js'
import { createPopover, type Popover } from '../functions/popover.js'
import { runBulkAction } from '../functions/runBulkAction.js'
import type { StyleFramework } from '../types/styleFramework.js'
import { confirmBulkAction } from './confirmModal.js'
import { type SelectionSnapshot, SelectionStore } from './selectionStore.js'

export interface BulkActionDefinition {
    name: string
    label: string
    className?: string
    icon?: string
    lucideIcon?: string
    confirm?: string
    confirmButton?: string
    successMessage?: string
    deselectAfterCompletion?: boolean
    denied?: boolean
}

export interface BulkActionLabels {
    trigger?: string
    selected?: string
    selectAllMatching?: string
    allMatchingSelected?: string
    clear?: string
    confirm?: string
    cancel?: string
    processed?: string
    skipped?: string
}

export interface BulkActionsConfig {
    actions: BulkActionDefinition[]
    selectCurrentPageOnly?: boolean
    url?: string | null
    labels?: BulkActionLabels
    position?: string
}

interface IconSource {
    icon?: string
    lucideIcon?: string
}

const BOOTSTRAP_FRAMEWORKS: StyleFramework[] = ['bs', 'bs4', 'bs5']

const KEBAB_ICON =
    '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="dt-bulk-trigger__icon">' +
    '<circle cx="10" cy="4" r="1.6" /><circle cx="10" cy="10" r="1.6" />' +
    '<circle cx="10" cy="16" r="1.6" /></svg>'

export function hasBulkActions(payload: Record<string, any>): boolean {
    const config = payload?.bulkActions

    return (
        config !== null &&
        typeof config === 'object' &&
        Array.isArray(config.actions) &&
        config.actions.length > 0
    )
}

export function getBulkActionsConfig(payload: Record<string, any>): BulkActionsConfig {
    return payload.bulkActions as BulkActionsConfig
}

/**
 * The bulk action trigger, its dropdown, and the selection band below the toolbar row.
 *
 * The bar only reports what the user picked; the server re-authorizes every row before the handler
 * sees it, so a forged selection buys nothing.
 */
export class BulkActionBar {
    private readonly config: BulkActionsConfig
    private readonly labels: BulkActionLabels
    private readonly wrapper: HTMLDivElement
    private readonly trigger: HTMLButtonElement
    private readonly menu: HTMLDivElement
    private readonly summary: HTMLDivElement
    private readonly summaryCount: HTMLSpanElement
    private readonly selectAllButton: HTMLButtonElement
    private readonly clearButton: HTMLButtonElement
    private readonly dataTable: string
    private readonly csrfToken?: string
    private readonly mutationsEnabled: boolean
    private popover: Popover | null = null
    private store: SelectionStore | null = null
    private api: any = null
    private running = false
    private resultMessage: string | null = null

    constructor(
        payload: Record<string, any>,
        private readonly framework: StyleFramework,
        private readonly dispatch: (
            name: string,
            detail: Record<string, unknown>
        ) => void = () => {}
    ) {
        this.config = getBulkActionsConfig(payload)
        this.labels = this.config.labels ?? {}
        this.dataTable = typeof payload.dataTable === 'string' ? payload.dataTable : ''
        this.csrfToken =
            typeof payload.csrfToken === 'string' && payload.csrfToken.length > 0
                ? payload.csrfToken
                : undefined
        this.mutationsEnabled = payload.mutationsEnabled === true

        this.wrapper = document.createElement('div')
        this.wrapper.className = 'dt-bulk'

        this.trigger = document.createElement('button')
        this.trigger.type = 'button'
        this.trigger.className = this.buttonClass('dt-bulk-trigger')
        this.trigger.disabled = true
        this.trigger.setAttribute('aria-haspopup', 'menu')
        this.trigger.setAttribute('aria-expanded', 'false')
        this.trigger.innerHTML = KEBAB_ICON
        this.trigger.appendChild(document.createTextNode(this.labels.trigger ?? 'Bulk actions'))

        this.menu = document.createElement('div')
        this.menu.className = 'dt-bulk-menu'
        this.menu.setAttribute('role', 'menu')
        this.menu.hidden = true

        this.wrapper.append(this.trigger, this.menu)

        this.summary = document.createElement('div')
        this.summary.className = 'dt-layout-row dt-layout-full dt-bulk-summary'
        this.setSummaryEmpty(true)

        this.summaryCount = document.createElement('span')
        this.summaryCount.className = 'dt-bulk-summary__count'
        this.summaryCount.setAttribute('role', 'status')

        this.selectAllButton = this.createLink(
            this.labels.selectAllMatching ?? 'Select all {count}',
            'dt-bulk-summary__select-all'
        )
        this.selectAllButton.hidden = true

        this.clearButton = this.createLink(
            this.labels.clear ?? 'Deselect all',
            'dt-bulk-summary__clear'
        )

        const summaryActions = document.createElement('div')
        summaryActions.className = 'dt-bulk-summary__actions'
        summaryActions.append(this.selectAllButton, this.clearButton)

        this.summary.append(this.summaryCount, summaryActions)
    }

    render(api: any): HTMLElement {
        this.api = api
        this.store = new SelectionStore(api)

        for (const action of this.config.actions) {
            this.menu.appendChild(this.createMenuItem(action))
        }

        this.popover = createPopover({
            wrapper: this.wrapper,
            panel: this.menu,
            toggle: this.trigger,
        })

        this.trigger.addEventListener('click', () => this.popover?.toggle())
        this.selectAllButton.addEventListener('click', () => this.store?.selectAllMatching())
        this.clearButton.addEventListener('click', () => this.store?.clear())

        this.store.attach((snapshot) => this.update(snapshot))

        // The feature runs while DataTables builds the layout, so the wrapper has no parent yet.
        queueMicrotask(() => this.mountSummary())

        return this.wrapper
    }

    /**
     * Put the selection band directly above the table, below every toolbar row.
     *
     * DataTables sorts a `full` layout row *above* the `topStart`/`topEnd` row of the same number,
     * so the layout option cannot place the band under the toolbar; the DOM can.
     */
    private mountSummary(): void {
        if (this.summary.isConnected) {
            return
        }

        const container =
            this.wrapper.closest('.dt-container') ??
            (this.api?.table?.().container?.() as HTMLElement | null | undefined)

        // The table row does not exist while the features render, so the mount happens on the
        // microtask queued at render time or, failing that, on the draw that follows.
        const tableRow = container?.querySelector('.dt-layout-table')

        tableRow?.parentNode?.insertBefore(this.summary, tableRow)
    }

    private update(snapshot: SelectionSnapshot): void {
        this.mountSummary()
        this.trigger.disabled = snapshot.count === 0 || !this.canRun()

        if (snapshot.count === 0) {
            this.popover?.close()
        }

        this.setSummaryEmpty(snapshot.count === 0 && this.resultMessage === null)
        this.summaryCount.textContent = this.resultMessage ?? this.countLabel(snapshot)

        this.selectAllButton.hidden =
            this.config.selectCurrentPageOnly === true ||
            snapshot.allMatching ||
            snapshot.count === 0 ||
            snapshot.count >= snapshot.totalCount
        this.selectAllButton.textContent = (
            this.labels.selectAllMatching ?? 'Select all {count}'
        ).replace('{count}', String(snapshot.totalCount))

        this.clearButton.hidden = snapshot.count === 0
    }

    /**
     * Reserve the band's height while it has nothing to say.
     *
     * The `hidden` attribute cannot do this: Tailwind's preflight declares `display: none` on it
     * with `!important`, so the band would leave the flow and resize the table on every selection.
     */
    private setSummaryEmpty(empty: boolean): void {
        this.summary.classList.toggle('dt-bulk-summary--empty', empty)
    }

    private countLabel(snapshot: SelectionSnapshot): string {
        const template = snapshot.allMatching
            ? (this.labels.allMatchingSelected ?? '{count} records selected across every page')
            : (this.labels.selected ?? '{count} records selected')

        return template.replace('{count}', String(snapshot.count))
    }

    private canRun(): boolean {
        return this.mutationsEnabled && !!this.config.url
    }

    private createMenuItem(action: BulkActionDefinition): HTMLButtonElement {
        const item = this.createButton(
            action.label,
            `dt-bulk-menu__item ${action.className ?? ''}`.trim(),
            action
        )
        item.dataset.bulkAction = action.name
        item.setAttribute('role', 'menuitem')

        if (action.denied === true || !this.canRun()) {
            item.disabled = true

            return item
        }

        item.addEventListener('click', () => {
            this.popover?.close()
            void this.execute(action)
        })

        return item
    }

    private async execute(action: BulkActionDefinition): Promise<void> {
        if (this.running || !this.store || !this.config.url) {
            return
        }

        const snapshot = this.store.snapshot()

        if (snapshot.count === 0) {
            return
        }

        if (action.confirm) {
            const confirmed = await confirmBulkAction({
                message: action.confirm.replace('{count}', String(snapshot.count)),
                confirmLabel: action.confirmButton ?? this.labels.confirm ?? 'Confirm',
                cancelLabel: this.labels.cancel ?? 'Cancel',
                framework: this.framework,
            })

            if (!confirmed) {
                return
            }
        }

        this.running = true
        this.trigger.setAttribute('aria-busy', 'true')
        this.trigger.disabled = true
        this.dispatch('bulk:start', { action: action.name, selection: snapshot })

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
            })

            this.resultMessage = this.summarize(action, result.processed, result.skipped)
            this.dispatch(result.success ? 'bulk:success' : 'bulk:error', {
                action: action.name,
                result,
            })

            if (result.success && action.deselectAfterCompletion !== false) {
                this.store.clear()
            }

            this.setSummaryEmpty(false)
            this.summaryCount.textContent = this.resultMessage
            this.reload()
        } catch (error) {
            this.dispatch('bulk:error', { action: action.name, error })
        } finally {
            this.running = false
            this.trigger.removeAttribute('aria-busy')
            this.trigger.disabled = this.store.snapshot().count === 0 || !this.canRun()
        }
    }

    private summarize(action: BulkActionDefinition, processed: number, skipped: number): string {
        const parts = [
            action.successMessage ??
                (this.labels.processed ?? '{count} rows processed').replace(
                    '{count}',
                    String(processed)
                ),
        ]

        if (skipped > 0) {
            parts.push(
                (this.labels.skipped ?? '{count} rows skipped').replace('{count}', String(skipped))
            )
        }

        return parts.join(' ')
    }

    /**
     * The parameters of the request the table is displaying, so a select-all resolves server-side
     * against the same search, ordering and filters the user sees.
     */
    private currentQuery(): Record<string, unknown> {
        const params = this.api?.ajax?.params?.()

        return params !== null && typeof params === 'object' ? params : {}
    }

    private reload(): void {
        this.api?.ajax?.reload?.(null, false)
    }

    private createButton(label: string, className: string, icon?: IconSource): HTMLButtonElement {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = className

        // The controller loads Lucide before the feature renders whenever a bulk action declares
        // an icon from the enum; a name outside the set renders nothing rather than a broken glyph.
        const lucide = icon?.lucideIcon
            ? renderLucideIcon(icon.lucideIcon, {
                  width: '1em',
                  height: '1em',
                  'aria-hidden': 'true',
              })
            : null

        if (null !== lucide) {
            button.insertAdjacentHTML('afterbegin', lucide)
        } else if (icon?.icon) {
            const iconElement = document.createElement('i')
            iconElement.className = icon.icon
            button.appendChild(iconElement)
        }

        button.appendChild(document.createTextNode(label))

        return button
    }

    private createLink(label: string, className: string): HTMLButtonElement {
        return this.createButton(label, `dt-bulk-summary__link ${className}`)
    }

    private buttonClass(className: string): string {
        return BOOTSTRAP_FRAMEWORKS.includes(this.framework)
            ? `btn btn-sm btn-outline-secondary ${className}`
            : `dt-button ${className}`
    }
}
