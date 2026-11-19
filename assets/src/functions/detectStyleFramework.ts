import { STYLE_FRAMEWORKS, type StyleFramework } from '../types/styleFramework.js'

let warnedAutodetection = false

/**
 * Detects which DataTables styling framework is loaded by inspecting
 * the page's stylesheets. Returns the framework key (e.g. 'bs5', 'dt').
 *
 * Falls back to 'dt' if no recognised stylesheet is found.
 */
export function detectStyleFramework(): StyleFramework {
    warnOnceAboutAutodetection()

    const sheets = [...document.styleSheets]

    for (const { key, cssPattern } of STYLE_FRAMEWORKS) {
        const matched = sheets.some(
            (sheet) => sheet.href !== null && sheet.href.includes(cssPattern)
        )

        if (matched) {
            return key
        }
    }

    console.warn(
        'No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".'
    )

    return 'dt'
}

/**
 * @deprecated since 1.6, set data_tables.style_framework or call styleFramework() on the table instead. Removed in 2.0.
 */
function warnOnceAboutAutodetection(): void {
    if (warnedAutodetection) {
        return
    }

    warnedAutodetection = true
    console.warn(
        'The style framework was autodetected from the page stylesheets, which is removed in 2.0. Set "style_framework" under "data_tables" in the bundle config, or call styleFramework() on the table.'
    )
}
