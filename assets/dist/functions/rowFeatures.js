import { applyEmptyState, bindEmptyStateReset } from './emptyState.js';
import { applyLocalLanguage } from './localLanguage.js';
import { bindRecordClick } from './recordUrl.js';
export async function applyTableLanguage(payload) {
    await applyLocalLanguage(payload);
    applyEmptyState(payload);
}
export function bindRowFeatures(payload, element, getTable, filterBar) {
    if (payload.recordUrl === true) {
        bindRecordClick(element);
    }
    if (filterBar && payload.emptyState) {
        bindEmptyStateReset(element, () => {
            const table = getTable();
            table?.search('');
            table?.columns?.().search('');
            filterBar.resetFilters();
        });
    }
}
//# sourceMappingURL=rowFeatures.js.map