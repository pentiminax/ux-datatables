import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js';
import { TailwindThemeColumnStyleAdapter } from './TailwindThemeColumnStyleAdapter.js';
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js';
export function resolveColumnStyleAdapter(framework, theme = null) {
    if (theme === 'tailwind') {
        return new TailwindThemeColumnStyleAdapter();
    }
    const factory = columnStyleAdapters.get(framework);
    if (!factory) {
        return new TailwindColumnStyleAdapter();
    }
    return factory();
}
//# sourceMappingURL=resolveColumnStyleAdapter.js.map