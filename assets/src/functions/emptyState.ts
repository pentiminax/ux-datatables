import { hasFilters } from './filters.js'
import { escapeHtml, isRecord } from './htmlUtils.js'
import { renderLucideIcon } from './lucideIcons.js'
import { isSafeRecordUrl } from './recordUrl.js'

interface EmptyStateLabels {
    noResults?: string
    noResultsHint?: string
    resetFilters?: string
}

interface EmptyStateConfig {
    heading: string
    description?: string | null
    icon?: string | null
    action?: { label: string; url: string } | null
    labels?: EmptyStateLabels
}

const RESET_ATTRIBUTE = 'data-dt-empty-reset'

function block(heading: string, description: string | null | undefined, icon: string): string {
    return (
        '<div class="dt-empty-state">' +
        icon +
        `<p class="dt-empty-state__heading">${escapeHtml(heading)}</p>` +
        (description ? `<p class="dt-empty-state__description">${escapeHtml(description)}</p>` : '')
    )
}

function iconHtml(name: string | null | undefined): string {
    const svg = name ? renderLucideIcon(name, { 'aria-hidden': 'true' }) : null

    return svg ? `<span class="dt-empty-state__icon">${svg}</span>` : ''
}

/** Shown when the table holds no data at all. */
export function buildEmptyHtml(config: EmptyStateConfig): string {
    const action = config.action
    const button =
        action && isSafeRecordUrl(action.url)
            ? `<a class="dt-empty-state__action" href="${escapeHtml(action.url)}">${escapeHtml(action.label)}</a>`
            : ''

    return `${block(config.heading, config.description, iconHtml(config.icon))}${button}</div>`
}

/** Shown when the current search or filters match nothing. */
export function buildNoResultsHtml(config: EmptyStateConfig, hasFilters: boolean): string {
    const labels = config.labels ?? {}
    const reset = hasFilters
        ? `<button type="button" class="dt-empty-state__action" ${RESET_ATTRIBUTE}>${escapeHtml(labels.resetFilters ?? 'Reset filters')}</button>`
        : ''

    return `${block(
        labels.noResults ?? 'No results found',
        labels.noResultsHint ?? 'Try a different search or adjust the filters.',
        iconHtml(config.icon)
    )}${reset}</div>`
}

/** Replaces the language strings of a table that declared an empty state. Run after the catalog loads. */
export function applyEmptyState(payload: Record<string, any>): void {
    const config = payload.emptyState

    if (!isRecord(config) || typeof config.heading !== 'string') {
        return
    }

    payload.language = {
        ...(isRecord(payload.language) ? payload.language : {}),
        emptyTable: buildEmptyHtml(config as EmptyStateConfig),
        zeroRecords: buildNoResultsHtml(config as EmptyStateConfig, hasFilters(payload)),
    }
}

export function bindEmptyStateReset(table: HTMLElement, reset: () => void): void {
    table.addEventListener('click', (event: MouseEvent): void => {
        if (event.target instanceof Element && event.target.closest(`[${RESET_ATTRIBUTE}]`)) {
            reset()
        }
    })
}
