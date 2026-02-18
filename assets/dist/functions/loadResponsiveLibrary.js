export async function loadResponsiveLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        ;
        (await import('datatables.net-responsive-bs5')).default;
    }
    else {
        ;
        (await import('datatables.net-responsive-dt')).default;
    }
}
//# sourceMappingURL=loadResponsiveLibrary.js.map