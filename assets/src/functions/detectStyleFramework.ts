import { STYLE_FRAMEWORKS, type StyleFramework } from '../types/styleFramework.js'

let warnedDeprecatedFramework = false

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
            warnOnceAboutDeprecatedFramework(key)

            return key
        }
    }

    console.warn(
        'No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".'
    )

    return 'dt'
}

function warnOnceAboutDeprecatedFramework(key: StyleFramework): void {
    if ((key !== 'bs' && key !== 'bs4') || warnedDeprecatedFramework) {
        return
    }

    warnedDeprecatedFramework = true
    console.warn(
        'Bootstrap 3 and 4 styling is deprecated and removed in 2.0. Load the Bootstrap 5 stylesheet ("bs5") or the DataTables stylesheet ("dt") instead.'
    )
}
