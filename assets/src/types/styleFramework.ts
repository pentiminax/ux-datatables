export type StyleFramework = 'dt' | 'bs5'

export interface StyleFrameworkConfig {
    key: StyleFramework
    cssPattern: string
}

/**
 * Ordered list of supported DataTables styling frameworks.
 *
 */
export const STYLE_FRAMEWORKS: StyleFrameworkConfig[] = [
    { key: 'bs5', cssPattern: 'dataTables.bootstrap5' },
    { key: 'dt', cssPattern: 'dataTables.dataTables' },
]

/**
 * Whether a value is a framework key the app declared explicitly (e.g. via the PHP
 * `styleFramework()` option), as opposed to `undefined` or some other payload value that
 * means autodetection should run instead.
 */
export function isStyleFramework(value: unknown): value is StyleFramework {
    return typeof value === 'string' && STYLE_FRAMEWORKS.some(({ key }) => key === value)
}
