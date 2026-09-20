import type { DataTableTheme } from '../functions/detectTheme.js'
import type { StyleFramework } from '../types/styleFramework.js'
import type { ColumnStyleAdapter } from './ColumnStyleAdapter.js'
import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js'
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js'

export function resolveColumnStyleAdapter(
    framework: StyleFramework,
    theme: DataTableTheme = null
): ColumnStyleAdapter {
    // The theme ships its own chrome styles, so it wins over the framework
    // default — otherwise a themed table would mix utility and semantic classes.
    // Both lookups go through the registry so a custom registration takes effect.
    const themeFactory = theme === null ? null : columnStyleAdapters.get(theme)
    const factory = themeFactory ?? columnStyleAdapters.get(framework)

    if (!factory) {
        return new TailwindColumnStyleAdapter()
    }

    return factory()
}
