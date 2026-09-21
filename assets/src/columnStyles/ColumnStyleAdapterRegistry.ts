import { BootstrapColumnStyleAdapter } from './BootstrapColumnStyleAdapter.js'
import type { ColumnStyleAdapterFactory } from './ColumnStyleAdapter.js'
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js'
import { TailwindThemeColumnStyleAdapter } from './TailwindThemeColumnStyleAdapter.js'

export class ColumnStyleAdapterRegistry {
    private readonly factories = new Map<string, ColumnStyleAdapterFactory>()

    register(key: string, factory: ColumnStyleAdapterFactory): this {
        this.factories.set(key, factory)

        return this
    }

    get(key: string): ColumnStyleAdapterFactory | null {
        return this.factories.get(key) ?? null
    }
}

/**
 * Keys are the `StyleFramework` values the PHP side serializes (`bs`, `bs4`, `bs5`, `dt`) plus the
 * `DataTableTheme` values `detectTheme()` reports — today only `tailwind`, which wins over the
 * framework key in `resolveColumnStyleAdapter()`.
 *
 * `dt` is the non-Bootstrap default rather than a DataTables chrome entry: DataTables.net ships no
 * badge or switch styles, so the utility-class adapter is what a Tailwind app without the bundle
 * theme needs. An app on plain `dt` with neither Tailwind nor the theme gets unstyled chrome; the
 * fix there is to enable `datatables-tailwind-theme.css`, which styles the `dt-badge` and
 * `dt-switch` classes the `tailwind` adapter emits.
 */
export const columnStyleAdapters = new ColumnStyleAdapterRegistry()
    .register('bs', () => new BootstrapColumnStyleAdapter())
    .register('bs4', () => new BootstrapColumnStyleAdapter())
    .register('bs5', () => new BootstrapColumnStyleAdapter())
    .register('dt', () => new TailwindColumnStyleAdapter())
    .register('tailwind', () => new TailwindThemeColumnStyleAdapter())
