import { STYLE_FRAMEWORKS, type StyleFramework } from '../types/styleFramework.js'

/**
 * Total time to keep retrying before falling back, and the gap between retries.
 *
 * Under Symfony's `fetch: "lazy"` controller loading, the framework's DataTables
 * stylesheet import can still be in flight when connect() runs — unlike `fetch: "eager"`,
 * where Encore/AssetMapper resolves it as part of the synchronous top-level bundle before
 * Application.start(). A short bounded retry absorbs that race without adding a
 * perceptible delay to the already-loaded case, which resolves on the first check.
 */
const RETRY_TIMEOUT_MS = 200
const RETRY_INTERVAL_MS = 20

/**
 * Detects which DataTables styling framework is loaded by inspecting
 * the page's stylesheets. Returns the framework key (e.g. 'bs5', 'dt').
 *
 * Retries for a short bounded window before falling back to 'dt', since the matching
 * stylesheet may not be in document.styleSheets yet on the very first check.
 */
export async function detectStyleFramework(): Promise<StyleFramework> {
    const deadline = Date.now() + RETRY_TIMEOUT_MS

    let framework = matchStyleFramework()
    while (null === framework && Date.now() < deadline) {
        await new Promise((resolve) => setTimeout(resolve, RETRY_INTERVAL_MS))
        framework = matchStyleFramework()
    }

    if (null !== framework) {
        return framework
    }

    console.warn(
        'No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".'
    )

    return 'dt'
}

function matchStyleFramework(): StyleFramework | null {
    const sheets = [...document.styleSheets]

    for (const { key, cssPattern } of STYLE_FRAMEWORKS) {
        const matched = sheets.some(
            (sheet) => sheet.href !== null && sheet.href.includes(cssPattern)
        )

        if (matched) {
            return key
        }
    }

    return null
}
