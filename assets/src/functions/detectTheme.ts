export type DataTableTheme = 'tailwind' | null

/**
 * Detects an opt-in bundle theme by reading the `--dt-tw-theme` custom property
 * the theme stylesheet declares on `:root`.
 *
 * A custom property is used rather than a stylesheet href match (as
 * `detectStyleFramework` does) because Encore and Vite concatenate the theme
 * into the application bundle, which erases the file name from `href`.
 */
export function detectTheme(): DataTableTheme {
    const value = getComputedStyle(document.documentElement).getPropertyValue('--dt-tw-theme').trim()

    return value === 'tailwind' ? 'tailwind' : null
}
