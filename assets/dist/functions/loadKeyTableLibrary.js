export async function loadKeyTableLibrary(stylesheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-keytable-bs5')).default;
    }
    else {
        (await import('datatables.net-keytable-dt')).default;
    }
}
