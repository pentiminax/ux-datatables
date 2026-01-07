export async function loadKeyTableLibrary(stylesheet?: CSSStyleSheet | null): Promise<void> {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-keytable-bs5')).default;
        (await import('datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css')).default;
    } else {
        (await import('datatables.net-keytable-dt')).default;
        (await import('datatables.net-keytable-dt/css/keyTable.dataTables.min.css')).default;
    }
}