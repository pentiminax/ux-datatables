import { packageLoaders } from '../functions/packageLoaders.js'

export type StyleFramework = 'dt' | 'bs5'

export const STYLE_FRAMEWORKS: StyleFramework[] = ['dt', 'bs5']

/**
 * Whether a value is a framework key the app declared explicitly (e.g. via the PHP
 * `styleFramework()` option), as opposed to `undefined` or some other payload value.
 */
export function isStyleFramework(value: unknown): value is StyleFramework {
    return typeof value === 'string' && (STYLE_FRAMEWORKS as string[]).includes(value)
}

/**
 * The style a table without a declared one uses: the one the application registered loaders for.
 * Loaders registered without a framework do not name a style, which then is `dt`.
 */
export function registeredStyleFramework(): StyleFramework {
    const registered = packageLoaders.frameworksOf('dataTables')

    if (registered.length > 1) {
        throw new Error(
            `Several styles are registered (${registered.join(', ')}). Set data_tables.style_framework or call styleFramework() on the table.`
        )
    }

    return registered[0] ?? 'dt'
}
