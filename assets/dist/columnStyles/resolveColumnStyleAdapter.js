import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js';
export function resolveColumnStyleAdapter(framework, theme = null) {
    const themeFactory = theme === null ? null : columnStyleAdapters.get(theme);
    const factory = themeFactory ?? columnStyleAdapters.get(framework);
    if (!factory) {
        throw new Error(`No column style adapter is registered for "${framework}". Register one with columnStyleAdapters.register('${framework}', factory).`);
    }
    return factory();
}
//# sourceMappingURL=resolveColumnStyleAdapter.js.map