import { STYLE_FRAMEWORKS, type StyleFramework } from '../types/styleFramework.js'

/**
 * Detects which DataTables styling framework is loaded by inspecting
 * the page's stylesheets. Returns the framework key (e.g. 'bs5', 'dt').
 *
 * Falls back to 'dt' if no recognised stylesheet is found.
 */
export function detectStyleFramework(): StyleFramework {
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
