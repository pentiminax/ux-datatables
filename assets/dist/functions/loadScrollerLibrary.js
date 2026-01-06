export async function loadScrollerLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-scroller-bs5')).default;
    }
    else {
        (await import('datatables.net-scroller-dt')).default;
    }
}
