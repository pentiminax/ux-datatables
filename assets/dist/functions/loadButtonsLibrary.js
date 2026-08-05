const frameworkLoaders = {
    dt: () => import('datatables.net-buttons-dt'),
    bs: () => import('datatables.net-buttons-bs'),
    bs4: () => import('datatables.net-buttons-bs4'),
    bs5: () => import('datatables.net-buttons-bs5'),
    bm: () => import('datatables.net-buttons-bm'),
    zf: () => import('datatables.net-buttons-zf'),
    jqui: () => import('datatables.net-buttons-jqui'),
    se: () => import('datatables.net-buttons-se'),
};
const frameworkStyleLoaders = {
    dt: () => import('datatables.net-buttons-dt/css/buttons.dataTables.min.css'),
    bs: () => import('datatables.net-buttons-bs/css/buttons.bootstrap.min.css'),
    bs4: () => import('datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css'),
    bs5: () => import('datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css'),
    bm: () => import('datatables.net-buttons-bm/css/buttons.bulma.min.css'),
    zf: () => import('datatables.net-buttons-zf/css/buttons.foundation.min.css'),
    jqui: () => import('datatables.net-buttons-jqui/css/buttons.jqueryui.min.css'),
    se: () => import('datatables.net-buttons-se/css/buttons.semanticui.min.css'),
};
export async function loadButtonsLibrary(DataTable, framework) {
    const [{ default: JSZip }, { default: pdfMake }] = await Promise.all([
        import('jszip'),
        import('pdfmake'),
    ]);
    await Promise.all([
        import('pdfmake/build/vfs_fonts'),
        import('datatables.net-buttons'),
        import('datatables.net-buttons/js/buttons.colVis'),
        import('datatables.net-buttons/js/buttons.html5'),
        import('datatables.net-buttons/js/buttons.print'),
        frameworkLoaders[framework](),
        frameworkStyleLoaders[framework](),
    ]);
    DataTable.Buttons.jszip(JSZip);
    DataTable.Buttons.pdfMake(pdfMake);
}
//# sourceMappingURL=loadButtonsLibrary.js.map