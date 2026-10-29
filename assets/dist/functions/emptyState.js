import { escapeHtml, isRecord } from './htmlUtils.js';
import { renderLucideIcon } from './lucideIcons.js';
import { isSafeRecordUrl } from './recordUrl.js';
const RESET_ATTRIBUTE = 'data-dt-empty-reset';
function block(heading, description, icon) {
    return ('<div class="dt-empty-state">' +
        icon +
        `<p class="dt-empty-state__heading">${escapeHtml(heading)}</p>` +
        (description ? `<p class="dt-empty-state__description">${escapeHtml(description)}</p>` : ''));
}
function iconHtml(name) {
    const svg = name ? renderLucideIcon(name, { 'aria-hidden': 'true' }) : null;
    return svg ? `<span class="dt-empty-state__icon">${svg}</span>` : '';
}
export function buildEmptyHtml(config) {
    const action = config.action;
    const button = action && isSafeRecordUrl(action.url)
        ? `<a class="dt-empty-state__action" href="${escapeHtml(action.url)}">${escapeHtml(action.label)}</a>`
        : '';
    return `${block(config.heading, config.description, iconHtml(config.icon))}${button}</div>`;
}
export function buildNoResultsHtml(config, hasFilters) {
    const labels = config.labels ?? {};
    const reset = hasFilters
        ? `<button type="button" class="dt-empty-state__action" ${RESET_ATTRIBUTE}>${escapeHtml(labels.resetFilters ?? 'Reset filters')}</button>`
        : '';
    return `${block(labels.noResults ?? 'No results found', labels.noResultsHint ?? 'Try a different search or adjust the filters.', iconHtml(config.icon))}${reset}</div>`;
}
export function applyEmptyState(payload) {
    const config = payload.emptyState;
    if (!isRecord(config) || typeof config.heading !== 'string') {
        return;
    }
    const hasFilters = Array.isArray(payload.filters) && payload.filters.length > 0;
    payload.language = {
        ...(isRecord(payload.language) ? payload.language : {}),
        emptyTable: buildEmptyHtml(config),
        zeroRecords: buildNoResultsHtml(config, hasFilters),
    };
}
export function bindEmptyStateReset(table, reset) {
    table.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest(`[${RESET_ATTRIBUTE}]`)) {
            reset();
        }
    });
}
//# sourceMappingURL=emptyState.js.map