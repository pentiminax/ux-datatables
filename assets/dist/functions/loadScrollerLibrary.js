export async function loadScrollerLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        ;
        (await import('datatables.net-scroller-bs5')).default;
        (await import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css')).default;
    }
    else {
        ;
        (await import('datatables.net-scroller-dt')).default;
        (await import('datatables.net-scroller-dt/css/scroller.dataTables.min.css')).default;
    }
}
//# sourceMappingURL=loadScrollerLibrary.js.map