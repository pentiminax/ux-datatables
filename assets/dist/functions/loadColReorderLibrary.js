export async function loadColReorderLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        ;
        (await import('datatables.net-colreorder-bs5')).default;
        (await import('datatables.net-colreorder-bs5/css/colReorder.bootstrap5.min.css')).default;
    }
    else {
        ;
        (await import('datatables.net-colreorder-dt')).default;
        (await import('datatables.net-colreorder-dt/css/colReorder.dataTables.min.css')).default;
    }
}
//# sourceMappingURL=loadColReorderLibrary.js.map