export async function loadColumnControlLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-columncontrol-bs5')).default;
        (await import('datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css')).default;
    }
    else {
        (await import('datatables.net-columncontrol-dt')).default;
        (await import('datatables.net-columncontrol-dt/css/columnControl.bootstrap5.min.css')).default;
    }
}
