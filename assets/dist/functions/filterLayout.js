import { applyFeatureLayout } from './featureLayout.js';
export function applyFilterLayout(payload, instance) {
    console.warn('applyFilterLayout() is deprecated and removed in 2.0. Use installFilterBar() instead.');
    applyFeatureLayout(payload, 'filters', { filters: { instance } }, {
        position: 'topEnd',
        before: ['search'],
    });
}
//# sourceMappingURL=filterLayout.js.map