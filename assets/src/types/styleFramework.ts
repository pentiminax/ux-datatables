export type StyleFramework = 'dt' | 'bs' | 'bs4' | 'bs5' | 'bm' | 'zf' | 'jqui' | 'se'

export interface StyleFrameworkConfig {
    key: StyleFramework
    cssPattern: string
}

/**
 * Ordered list of supported DataTables styling frameworks.
 *
 * Order matters: more specific patterns must come before less specific ones.
 * 'bootstrap5' must precede 'bootstrap4', which must precede 'bootstrap',
 * to prevent a partial href match from short-circuiting detection.
 * 'dt' is last and serves as fallback.
 */
export const STYLE_FRAMEWORKS: StyleFrameworkConfig[] = [
    { key: 'bs5', cssPattern: 'dataTables.bootstrap5' },
    { key: 'bs4', cssPattern: 'dataTables.bootstrap4' },
    { key: 'bs', cssPattern: 'dataTables.bootstrap' },
    { key: 'bm', cssPattern: 'dataTables.bulma' },
    { key: 'zf', cssPattern: 'dataTables.foundation' },
    { key: 'jqui', cssPattern: 'dataTables.jqueryui' },
    { key: 'se', cssPattern: 'dataTables.semanticui' },
    { key: 'dt', cssPattern: 'dataTables.dataTables' },
]
