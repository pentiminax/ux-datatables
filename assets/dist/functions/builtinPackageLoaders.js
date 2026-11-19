import { packageLoaders } from './packageLoaders.js';
const frameworkLoaders = {
    dataTables: {
        dt: () => import('datatables.net-dt'),
        bs5: () => import('datatables.net-bs5'),
    },
    colReorder: {
        dt: async () => {
            await import('datatables.net-colreorder-dt');
            await import('datatables.net-colreorder-dt/css/colReorder.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-colreorder-bs5');
            await import('datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css');
        },
    },
    columnControl: {
        dt: async () => {
            await import('datatables.net-columncontrol-dt');
            await import('datatables.net-columncontrol-dt/css/columnControl.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-columncontrol-bs5');
            await import('datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css');
        },
    },
    fixedColumns: {
        dt: async () => {
            await import('datatables.net-fixedcolumns-dt');
            await import('datatables.net-fixedcolumns-dt/css/fixedColumns.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-fixedcolumns-bs5');
            await import('datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css');
        },
    },
    fixedHeader: {
        dt: async () => {
            await import('datatables.net-fixedheader-dt');
            await import('datatables.net-fixedheader-dt/css/fixedHeader.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-fixedheader-bs5');
            await import('datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css');
        },
    },
    keyTable: {
        dt: async () => {
            await import('datatables.net-keytable-dt');
            await import('datatables.net-keytable-dt/css/keyTable.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-keytable-bs5');
            await import('datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css');
        },
    },
    responsive: {
        dt: async () => {
            await import('datatables.net-responsive-dt');
            await import('datatables.net-responsive-dt/css/responsive.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-responsive-bs5');
            await import('datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css');
        },
    },
    rowGroup: {
        dt: async () => {
            await import('datatables.net-rowgroup-dt');
            await import('datatables.net-rowgroup-dt/css/rowGroup.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-rowgroup-bs5');
            await import('datatables.net-rowgroup-bs5/css/rowGroup.bootstrap5.min.css');
        },
    },
    scroller: {
        dt: async () => {
            await import('datatables.net-scroller-dt');
            await import('datatables.net-scroller-dt/css/scroller.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-scroller-bs5');
            await import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css');
        },
    },
    searchBuilder: {
        dt: async () => {
            await import('datatables.net-datetime');
            await import('datatables.net-searchbuilder-dt');
            await import('datatables.net-searchbuilder-dt/css/searchBuilder.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-datetime');
            await import('datatables.net-searchbuilder-bs5');
            await import('datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css');
        },
    },
    select: {
        dt: async () => {
            await import('datatables.net-select-dt');
            await import('datatables.net-select-dt/css/select.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-select-bs5');
            await import('datatables.net-select-bs5/css/select.bootstrap5.min.css');
        },
    },
    buttons: {
        dt: async () => {
            await import('datatables.net-buttons-dt');
            await import('datatables.net-buttons-dt/css/buttons.dataTables.min.css');
        },
        bs5: async () => {
            await import('datatables.net-buttons-bs5');
            await import('datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css');
        },
    },
};
const sharedLoaders = {
    bootstrap: () => import('bootstrap'),
    jszip: () => import('jszip'),
    lucide: () => import('lucide'),
    pdfmake: async () => {
        const pdfMake = await import('pdfmake');
        await import('pdfmake/build/vfs_fonts.js');
        return pdfMake;
    },
};
packageLoaders.setFallback((name, framework) => sharedLoaders[name] ?? frameworkLoaders[name]?.[framework ?? 'dt']);
//# sourceMappingURL=builtinPackageLoaders.js.map