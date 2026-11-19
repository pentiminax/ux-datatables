import { packageLoaders } from '../functions/packageLoaders.js';
export const STYLE_FRAMEWORKS = ['dt', 'bs5'];
export function isStyleFramework(value) {
    return typeof value === 'string' && STYLE_FRAMEWORKS.includes(value);
}
export function registeredStyleFramework() {
    const registered = packageLoaders.frameworksOf('dataTables');
    if (registered.length > 1) {
        throw new Error(`Several styles are registered (${registered.join(', ')}). Set data_tables.style_framework or call styleFramework() on the table.`);
    }
    return registered[0] ?? 'dt';
}
//# sourceMappingURL=styleFramework.js.map