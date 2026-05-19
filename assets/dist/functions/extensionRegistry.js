const EXTENSION_PKG_KEY = {
    colReorder: 'colreorder',
    columnControl: 'columncontrol',
    fixedColumns: 'fixedcolumns',
    keyTable: 'keytable',
    responsive: 'responsive',
    scroller: 'scroller',
    select: 'select',
};
const EXTENSION_FILE_BASE = {
    colReorder: 'colReorder',
    columnControl: 'columnControl',
    fixedColumns: 'fixedColumns',
    keyTable: 'keyTable',
    responsive: 'responsive',
    scroller: 'scroller',
    select: 'select',
};
const FRAMEWORK_CSS_SUFFIX = {
    dt: 'dataTables',
    bs: 'bootstrap',
    bs4: 'bootstrap4',
    bs5: 'bootstrap5',
    bm: 'bulma',
    zf: 'foundation',
    jqui: 'jqueryui',
    se: 'semanticui',
};
export class ExtensionRegistry {
    static async load(name, framework) {
        const pkgKey = EXTENSION_PKG_KEY[name];
        const fileBase = EXTENSION_FILE_BASE[name];
        if (!pkgKey || !fileBase) {
            throw new Error(`Unknown extension: "${name}"`);
        }
        const cssSuffix = FRAMEWORK_CSS_SUFFIX[framework];
        const jsSpecifier = `datatables.net-${pkgKey}-${framework}`;
        const cssSpecifier = `datatables.net-${pkgKey}-${framework}/css/${fileBase}.${cssSuffix}.min.css`;
        await import(jsSpecifier);
        await import(cssSpecifier);
    }
}
//# sourceMappingURL=extensionRegistry.js.map