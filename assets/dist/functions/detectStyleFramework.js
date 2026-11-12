import { STYLE_FRAMEWORKS } from '../types/styleFramework.js';
let warnedDeprecatedFramework = false;
let warnedAutodetection = false;
export function detectStyleFramework() {
    warnOnceAboutAutodetection();
    const sheets = [...document.styleSheets];
    for (const { key, cssPattern } of STYLE_FRAMEWORKS) {
        const matched = sheets.some((sheet) => sheet.href !== null && sheet.href.includes(cssPattern));
        if (matched) {
            warnOnceAboutDeprecatedFramework(key);
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
    console.warn('The style framework was autodetected from the page stylesheets, which is removed in 2.0. Call styleFramework() on the table or install a style kit.');
}
function warnOnceAboutDeprecatedFramework(key) {
    if ((key !== 'bs' && key !== 'bs4') || warnedDeprecatedFramework) {
        return;
    }
    warnedDeprecatedFramework = true;
    console.warn('Bootstrap 3 and 4 styling is deprecated and removed in 2.0. Load the Bootstrap 5 stylesheet ("bs5") or the DataTables stylesheet ("dt") instead.');
}
//# sourceMappingURL=detectStyleFramework.js.map