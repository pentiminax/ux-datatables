import { STYLE_FRAMEWORKS } from '../types/styleFramework.js';
const RETRY_TIMEOUT_MS = 200;
const RETRY_INTERVAL_MS = 20;
export async function detectStyleFramework() {
    const deadline = Date.now() + RETRY_TIMEOUT_MS;
    let framework = matchStyleFramework();
    while (null === framework && Date.now() < deadline) {
        await new Promise((resolve) => setTimeout(resolve, RETRY_INTERVAL_MS));
        framework = matchStyleFramework();
    }
    if (null !== framework) {
        return framework;
    }
    console.warn('No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".');
    return 'dt';
}
function matchStyleFramework() {
    const sheets = [...document.styleSheets];
    for (const { key, cssPattern } of STYLE_FRAMEWORKS) {
        const matched = sheets.some((sheet) => sheet.href !== null && sheet.href.includes(cssPattern));
        if (matched) {
            return key;
        }
    }
    return null;
}
//# sourceMappingURL=detectStyleFramework.js.map