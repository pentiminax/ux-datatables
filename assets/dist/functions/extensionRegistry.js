const registry = {
    colReorder: {
        js: {
            dt: () => import('datatables.net-colreorder-dt'),
            bs: () => import('datatables.net-colreorder-bs'),
            bs4: () => import('datatables.net-colreorder-bs4'),
            bs5: () => import('datatables.net-colreorder-bs5'),
            bm: () => import('datatables.net-colreorder-bm'),
            zf: () => import('datatables.net-colreorder-zf'),
            jqui: () => import('datatables.net-colreorder-jqui'),
            se: () => import('datatables.net-colreorder-se'),
        },
        css: {
            dt: () => import('datatables.net-colreorder-dt/css/colReorder.dataTables.min.css'),
            bs: () => import('datatables.net-colreorder-bs/css/colReorder.bootstrap.min.css'),
            bs4: () => import('datatables.net-colreorder-bs4/css/colReorder.bootstrap4.min.css'),
            bs5: () => import('datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css'),
            bm: () => import('datatables.net-colreorder-bm/css/colReorder.bulma.min.css'),
            zf: () => import('datatables.net-colreorder-zf/css/colReorder.foundation.min.css'),
            jqui: () => import('datatables.net-colreorder-jqui/css/colReorder.jqueryui.min.css'),
            se: () => import('datatables.net-colreorder-se/css/colReorder.semanticui.min.css'),
        },
    },
    columnControl: {
        js: {
            dt: () => import('datatables.net-columncontrol-dt'),
            bs: () => import('datatables.net-columncontrol-bs'),
            bs4: () => import('datatables.net-columncontrol-bs4'),
            bs5: () => import('datatables.net-columncontrol-bs5'),
            bm: () => import('datatables.net-columncontrol-bm'),
            zf: () => import('datatables.net-columncontrol-zf'),
            jqui: () => import('datatables.net-columncontrol-jqui'),
            se: () => import('datatables.net-columncontrol-se'),
        },
        css: {
            dt: () => import('datatables.net-columncontrol-dt/css/columnControl.dataTables.min.css'),
            bs: () => import('datatables.net-columncontrol-bs/css/columnControl.bootstrap.min.css'),
            bs4: () => import('datatables.net-columncontrol-bs4/css/columnControl.bootstrap4.min.css'),
            bs5: () => import('datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css'),
            bm: () => import('datatables.net-columncontrol-bm/css/columnControl.bulma.min.css'),
            zf: () => import('datatables.net-columncontrol-zf/css/columnControl.foundation.min.css'),
            jqui: () => import('datatables.net-columncontrol-jqui/css/columnControl.jqueryui.min.css'),
            se: () => import('datatables.net-columncontrol-se/css/columnControl.semanticui.min.css'),
        },
    },
    fixedColumns: {
        js: {
            dt: () => import('datatables.net-fixedcolumns-dt'),
            bs: () => import('datatables.net-fixedcolumns-bs'),
            bs4: () => import('datatables.net-fixedcolumns-bs4'),
            bs5: () => import('datatables.net-fixedcolumns-bs5'),
            bm: () => import('datatables.net-fixedcolumns-bm'),
            zf: () => import('datatables.net-fixedcolumns-zf'),
            jqui: () => import('datatables.net-fixedcolumns-jqui'),
            se: () => import('datatables.net-fixedcolumns-se'),
        },
        css: {
            dt: () => import('datatables.net-fixedcolumns-dt/css/fixedColumns.dataTables.min.css'),
            bs: () => import('datatables.net-fixedcolumns-bs/css/fixedColumns.bootstrap.min.css'),
            bs4: () => import('datatables.net-fixedcolumns-bs4/css/fixedColumns.bootstrap4.min.css'),
            bs5: () => import('datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css'),
            bm: () => import('datatables.net-fixedcolumns-bm/css/fixedColumns.bulma.min.css'),
            zf: () => import('datatables.net-fixedcolumns-zf/css/fixedColumns.foundation.min.css'),
            jqui: () => import('datatables.net-fixedcolumns-jqui/css/fixedColumns.jqueryui.min.css'),
            se: () => import('datatables.net-fixedcolumns-se/css/fixedColumns.semanticui.min.css'),
        },
    },
    keyTable: {
        js: {
            dt: () => import('datatables.net-keytable-dt'),
            bs: () => import('datatables.net-keytable-bs'),
            bs4: () => import('datatables.net-keytable-bs4'),
            bs5: () => import('datatables.net-keytable-bs5'),
            bm: () => import('datatables.net-keytable-bm'),
            zf: () => import('datatables.net-keytable-zf'),
            jqui: () => import('datatables.net-keytable-jqui'),
            se: () => import('datatables.net-keytable-se'),
        },
        css: {
            dt: () => import('datatables.net-keytable-dt/css/keyTable.dataTables.min.css'),
            bs: () => import('datatables.net-keytable-bs/css/keyTable.bootstrap.min.css'),
            bs4: () => import('datatables.net-keytable-bs4/css/keyTable.bootstrap4.min.css'),
            bs5: () => import('datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css'),
            bm: () => import('datatables.net-keytable-bm/css/keyTable.bulma.min.css'),
            zf: () => import('datatables.net-keytable-zf/css/keyTable.foundation.min.css'),
            jqui: () => import('datatables.net-keytable-jqui/css/keyTable.jqueryui.min.css'),
            se: () => import('datatables.net-keytable-se/css/keyTable.semanticui.min.css'),
        },
    },
    responsive: {
        js: {
            dt: () => import('datatables.net-responsive-dt'),
            bs: () => import('datatables.net-responsive-bs'),
            bs4: () => import('datatables.net-responsive-bs4'),
            bs5: () => import('datatables.net-responsive-bs5'),
            bm: () => import('datatables.net-responsive-bm'),
            zf: () => import('datatables.net-responsive-zf'),
            jqui: () => import('datatables.net-responsive-jqui'),
            se: () => import('datatables.net-responsive-se'),
        },
        css: {
            dt: () => import('datatables.net-responsive-dt/css/responsive.dataTables.min.css'),
            bs: () => import('datatables.net-responsive-bs/css/responsive.bootstrap.min.css'),
            bs4: () => import('datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css'),
            bs5: () => import('datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css'),
            bm: () => import('datatables.net-responsive-bm/css/responsive.bulma.min.css'),
            zf: () => import('datatables.net-responsive-zf/css/responsive.foundation.min.css'),
            jqui: () => import('datatables.net-responsive-jqui/css/responsive.jqueryui.min.css'),
            se: () => import('datatables.net-responsive-se/css/responsive.semanticui.min.css'),
        },
    },
    scroller: {
        js: {
            dt: () => import('datatables.net-scroller-dt'),
            bs: () => import('datatables.net-scroller-bs'),
            bs4: () => import('datatables.net-scroller-bs4'),
            bs5: () => import('datatables.net-scroller-bs5'),
            bm: () => import('datatables.net-scroller-bm'),
            zf: () => import('datatables.net-scroller-zf'),
            jqui: () => import('datatables.net-scroller-jqui'),
            se: () => import('datatables.net-scroller-se'),
        },
        css: {
            dt: () => import('datatables.net-scroller-dt/css/scroller.dataTables.min.css'),
            bs: () => import('datatables.net-scroller-bs/css/scroller.bootstrap.min.css'),
            bs4: () => import('datatables.net-scroller-bs4/css/scroller.bootstrap4.min.css'),
            bs5: () => import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css'),
            bm: () => import('datatables.net-scroller-bm/css/scroller.bulma.min.css'),
            zf: () => import('datatables.net-scroller-zf/css/scroller.foundation.min.css'),
            jqui: () => import('datatables.net-scroller-jqui/css/scroller.jqueryui.min.css'),
            se: () => import('datatables.net-scroller-se/css/scroller.semanticui.min.css'),
        },
    },
    select: {
        js: {
            dt: () => import('datatables.net-select-dt'),
            bs: () => import('datatables.net-select-bs'),
            bs4: () => import('datatables.net-select-bs4'),
            bs5: () => import('datatables.net-select-bs5'),
            bm: () => import('datatables.net-select-bm'),
            zf: () => import('datatables.net-select-zf'),
            jqui: () => import('datatables.net-select-jqui'),
            se: () => import('datatables.net-select-se'),
        },
        css: {
            dt: () => import('datatables.net-select-dt/css/select.dataTables.min.css'),
            bs: () => import('datatables.net-select-bs/css/select.bootstrap.min.css'),
            bs4: () => import('datatables.net-select-bs4/css/select.bootstrap4.min.css'),
            bs5: () => import('datatables.net-select-bs5/css/select.bootstrap5.min.css'),
            bm: () => import('datatables.net-select-bm/css/select.bulma.min.css'),
            zf: () => import('datatables.net-select-zf/css/select.foundation.min.css'),
            jqui: () => import('datatables.net-select-jqui/css/select.jqueryui.min.css'),
            se: () => import('datatables.net-select-se/css/select.semanticui.min.css'),
        },
    },
};
export class ExtensionRegistry {
    static async load(name, framework) {
        const ext = registry[name];
        if (!ext) {
            throw new Error(`Unknown extension: "${name}"`);
        }
        await ext.js[framework]();
        await ext.css[framework]();
    }
}
//# sourceMappingURL=extensionRegistry.js.map