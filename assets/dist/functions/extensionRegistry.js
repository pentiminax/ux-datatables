const extensionLoaders = {
    colReorder: {
        dt: () => import('datatables.net-colreorder-dt'),
        bs: () => import('datatables.net-colreorder-bs'),
        bs4: () => import('datatables.net-colreorder-bs4'),
        bs5: () => import('datatables.net-colreorder-bs5'),
    },
    columnControl: {
        dt: () => import('datatables.net-columncontrol-dt'),
        bs: () => import('datatables.net-columncontrol-bs'),
        bs4: () => import('datatables.net-columncontrol-bs4'),
        bs5: () => import('datatables.net-columncontrol-bs5'),
    },
    fixedColumns: {
        dt: () => import('datatables.net-fixedcolumns-dt'),
        bs: () => import('datatables.net-fixedcolumns-bs'),
        bs4: () => import('datatables.net-fixedcolumns-bs4'),
        bs5: () => import('datatables.net-fixedcolumns-bs5'),
    },
    fixedHeader: {
        dt: () => import('datatables.net-fixedheader-dt'),
        bs: () => import('datatables.net-fixedheader-bs'),
        bs4: () => import('datatables.net-fixedheader-bs4'),
        bs5: () => import('datatables.net-fixedheader-bs5'),
    },
    keyTable: {
        dt: () => import('datatables.net-keytable-dt'),
        bs: () => import('datatables.net-keytable-bs'),
        bs4: () => import('datatables.net-keytable-bs4'),
        bs5: () => import('datatables.net-keytable-bs5'),
    },
    responsive: {
        dt: () => import('datatables.net-responsive-dt'),
        bs: () => import('datatables.net-responsive-bs'),
        bs4: () => import('datatables.net-responsive-bs4'),
        bs5: () => import('datatables.net-responsive-bs5'),
    },
    rowGroup: {
        dt: () => import('datatables.net-rowgroup-dt'),
        bs: () => import('datatables.net-rowgroup-bs'),
        bs4: () => import('datatables.net-rowgroup-bs4'),
        bs5: () => import('datatables.net-rowgroup-bs5'),
    },
    scroller: {
        dt: () => import('datatables.net-scroller-dt'),
        bs: () => import('datatables.net-scroller-bs'),
        bs4: () => import('datatables.net-scroller-bs4'),
        bs5: () => import('datatables.net-scroller-bs5'),
    },
    select: {
        dt: () => import('datatables.net-select-dt'),
        bs: () => import('datatables.net-select-bs'),
        bs4: () => import('datatables.net-select-bs4'),
        bs5: () => import('datatables.net-select-bs5'),
    },
};
const extensionStyleLoaders = {
    colReorder: {
        dt: () => import('datatables.net-colreorder-dt/css/colReorder.dataTables.min.css'),
        bs: () => import('datatables.net-colreorder-bs/css/colReorder.bootstrap.min.css'),
        bs4: () => import('datatables.net-colreorder-bs4/css/colReorder.bootstrap4.min.css'),
        bs5: () => import('datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css'),
    },
    columnControl: {
        dt: () => import('datatables.net-columncontrol-dt/css/columnControl.dataTables.min.css'),
        bs: () => import('datatables.net-columncontrol-bs/css/columnControl.bootstrap.min.css'),
        bs4: () => import('datatables.net-columncontrol-bs4/css/columnControl.bootstrap4.min.css'),
        bs5: () => import('datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css'),
    },
    fixedColumns: {
        dt: () => import('datatables.net-fixedcolumns-dt/css/fixedColumns.dataTables.min.css'),
        bs: () => import('datatables.net-fixedcolumns-bs/css/fixedColumns.bootstrap.min.css'),
        bs4: () => import('datatables.net-fixedcolumns-bs4/css/fixedColumns.bootstrap4.min.css'),
        bs5: () => import('datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css'),
    },
    fixedHeader: {
        dt: () => import('datatables.net-fixedheader-dt/css/fixedHeader.dataTables.min.css'),
        bs: () => import('datatables.net-fixedheader-bs/css/fixedHeader.bootstrap.min.css'),
        bs4: () => import('datatables.net-fixedheader-bs4/css/fixedHeader.bootstrap4.min.css'),
        bs5: () => import('datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css'),
    },
    keyTable: {
        dt: () => import('datatables.net-keytable-dt/css/keyTable.dataTables.min.css'),
        bs: () => import('datatables.net-keytable-bs/css/keyTable.bootstrap.min.css'),
        bs4: () => import('datatables.net-keytable-bs4/css/keyTable.bootstrap4.min.css'),
        bs5: () => import('datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css'),
    },
    responsive: {
        dt: () => import('datatables.net-responsive-dt/css/responsive.dataTables.min.css'),
        bs: () => import('datatables.net-responsive-bs/css/responsive.bootstrap.min.css'),
        bs4: () => import('datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css'),
        bs5: () => import('datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css'),
    },
    rowGroup: {
        dt: () => import('datatables.net-rowgroup-dt/css/rowGroup.dataTables.min.css'),
        bs: () => import('datatables.net-rowgroup-bs/css/rowGroup.bootstrap.min.css'),
        bs4: () => import('datatables.net-rowgroup-bs4/css/rowGroup.bootstrap4.min.css'),
        bs5: () => import('datatables.net-rowgroup-bs5/css/rowGroup.bootstrap5.min.css'),
    },
    scroller: {
        dt: () => import('datatables.net-scroller-dt/css/scroller.dataTables.min.css'),
        bs: () => import('datatables.net-scroller-bs/css/scroller.bootstrap.min.css'),
        bs4: () => import('datatables.net-scroller-bs4/css/scroller.bootstrap4.min.css'),
        bs5: () => import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css'),
    },
    select: {
        dt: () => import('datatables.net-select-dt/css/select.dataTables.min.css'),
        bs: () => import('datatables.net-select-bs/css/select.bootstrap.min.css'),
        bs4: () => import('datatables.net-select-bs4/css/select.bootstrap4.min.css'),
        bs5: () => import('datatables.net-select-bs5/css/select.bootstrap5.min.css'),
    },
};
export class ExtensionRegistry {
    static async load(name, framework) {
        const loader = extensionLoaders[name];
        const styleLoader = extensionStyleLoaders[name];
        if (!loader || !styleLoader) {
            throw new Error(`Unknown extension: "${name}"`);
        }
        await loader[framework]();
        await styleLoader[framework]();
    }
}
//# sourceMappingURL=extensionRegistry.js.map