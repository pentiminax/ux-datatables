import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js';
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js';
export function resolveColumnStyleAdapter(framework, theme = null) {
    const themeFactory = theme === null ? null : columnStyleAdapters.get(theme);
    const factory = themeFactory ?? columnStyleAdapters.get(framework);
    if (!factory) {
        return new TailwindColumnStyleAdapter();
    }
    return factory();
}
//# sourceMappingURL=resolveColumnStyleAdapter.js.map