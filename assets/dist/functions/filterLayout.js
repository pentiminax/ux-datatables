import { applyFeatureLayout } from './featureLayout.js';
export function applyFilterLayout(payload, instance) {
    applyFeatureLayout(payload, 'filters', { filters: { instance } }, {
        position: 'topEnd',
        before: ['search'],
    });
}
//# sourceMappingURL=filterLayout.js.map