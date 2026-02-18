export function getLoadedDataTablesStyleSheet() {
    const cssFiles = ['dataTables.dataTables', 'dataTables.bootstrap5'];
    const loadedCSS = [...document.styleSheets].find((sheet) => sheet.href && cssFiles.some((cssFile) => sheet.href.includes(cssFile)));
    if (!loadedCSS) {
        console.warn('Warning: Required DataTables CSS file is not loaded.');
        return null;
    }
    return loadedCSS;
}
//# sourceMappingURL=getLoadedDataTablesStyleSheet.js.map