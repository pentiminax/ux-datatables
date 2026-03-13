const registry = {
    colReorder: {
        bs5: () => import('datatables.net-colreorder-bs5'),
        dt: () => import('datatables.net-colreorder-dt'),
        cssBs5: () => import('datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css'),
        cssDt: () => import('datatables.net-colreorder-dt/css/colReorder.dataTables.min.css'),
    },
    columnControl: {
        bs5: () => import('datatables.net-columncontrol-bs5'),
        dt: () => import('datatables.net-columncontrol-dt'),
        cssBs5: () => import('datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css'),
        cssDt: () => import('datatables.net-columncontrol-dt/css/columnControl.dataTables.min.css'),
    },
    fixedColumns: {
        bs5: () => import('datatables.net-fixedcolumns-bs5'),
        dt: () => import('datatables.net-fixedcolumns-dt'),
        cssBs5: () => import('datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css'),
        cssDt: () => import('datatables.net-fixedcolumns-dt/css/fixedColumns.dataTables.min.css'),
    },
    keyTable: {
        bs5: () => import('datatables.net-keytable-bs5'),
        dt: () => import('datatables.net-keytable-dt'),
        cssBs5: () => import('datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css'),
        cssDt: () => import('datatables.net-keytable-dt/css/keyTable.dataTables.min.css'),
    },
    responsive: {
        bs5: () => import('datatables.net-responsive-bs5'),
        dt: () => import('datatables.net-responsive-dt'),
    },
    scroller: {
        bs5: () => import('datatables.net-scroller-bs5'),
        dt: () => import('datatables.net-scroller-dt'),
        cssBs5: () => import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css'),
        cssDt: () => import('datatables.net-scroller-dt/css/scroller.dataTables.min.css'),
    },
    select: {
        bs5: () => import('datatables.net-select-bs5'),
        dt: () => import('datatables.net-select-dt'),
    },
};
export class ExtensionRegistry {
    static async load(name, stylesheet) {
        const ext = registry[name];
        if (!ext) {
            throw new Error(`Unknown extension: ${name}`);
        }
        const isBs5 = stylesheet?.href?.includes('dataTables.bootstrap5');
        if (isBs5) {
            await ext.bs5();
            if (ext.cssBs5)
                await ext.cssBs5();
        }
        else {
            await ext.dt();
            if (ext.cssDt)
                await ext.cssDt();
        }
    }
}
//# sourceMappingURL=extensionRegistry.js.map