import { STYLE_FRAMEWORKS } from '../types/styleFramework.js';
let warnedAutodetection = false;
export function detectStyleFramework() {
    warnOnceAboutAutodetection();
    const sheets = [...document.styleSheets];
    for (const { key, cssPattern } of STYLE_FRAMEWORKS) {
        const matched = sheets.some((sheet) => sheet.href !== null && sheet.href.includes(cssPattern));
        if (matched) {
            return key;
        }
    }
    console.warn('No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".');
    return 'dt';
}
function warnOnceAboutAutodetection() {
    if (warnedAutodetection) {
        return;
    }
    warnedAutodetection = true;
    console.warn('The style framework was autodetected from the page stylesheets, which is removed in 2.0. Set "style_framework" under "data_tables" in the bundle config, or call styleFramework() on the table.');
}
//# sourceMappingURL=detectStyleFramework.js.map