import type { DataTableTheme } from '../functions/detectTheme.js'
import type { StyleFramework } from '../types/styleFramework.js'
import type { ColumnStyleAdapter } from './ColumnStyleAdapter.js'
import { columnStyleAdapters } from './ColumnStyleAdapterRegistry.js'

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
        throw new Error(
            `No column style adapter is registered for "${framework}". Register one with columnStyleAdapters.register('${framework}', factory).`
        )
    }

    return factory()
}
