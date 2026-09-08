import type { DataTableTheme } from '../functions/detectTheme.js'
import type { StyleFramework } from '../types/styleFramework.js'
import type { ColumnStyleAdapter } from './ColumnStyleAdapter.js'
import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js'
import { TailwindThemeColumnStyleAdapter } from './TailwindThemeColumnStyleAdapter.js'
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js'

export function resolveColumnStyleAdapter(
    framework: StyleFramework,
    theme: DataTableTheme = null
): ColumnStyleAdapter {
    // The theme ships its own chrome styles, so it wins over the framework
    // default — otherwise a themed table would mix utility and semantic classes.
    if (theme === 'tailwind') {
        return new TailwindThemeColumnStyleAdapter()
    }

    const factory = columnStyleAdapters.get(framework)

    if (!factory) {
        return new TailwindColumnStyleAdapter()
    }

    return factory()
}
