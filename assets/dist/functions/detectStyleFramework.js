import { STYLE_FRAMEWORKS } from '../types/styleFramework.js';
export function detectStyleFramework() {
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
//# sourceMappingURL=detectStyleFramework.js.map