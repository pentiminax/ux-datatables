import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js';
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js';
export function resolveColumnStyleAdapter(framework) {
    const factory = columnStyleAdapters.get(framework);
    if (!factory) {
        return new TailwindColumnStyleAdapter();
    }
    return factory();
}
//# sourceMappingURL=resolveColumnStyleAdapter.js.map