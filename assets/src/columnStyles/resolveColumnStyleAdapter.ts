import type { StyleFramework } from '../types/styleFramework.js'
import type { ColumnStyleAdapter } from './ColumnStyleAdapter.js'
import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js'
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js'

export function resolveColumnStyleAdapter(framework: StyleFramework): ColumnStyleAdapter {
    const factory = columnStyleAdapters.get(framework)

    if (!factory) {
        return new TailwindColumnStyleAdapter()
    }

    return factory()
}
