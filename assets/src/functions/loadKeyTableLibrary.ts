export async function loadKeyTableLibrary(stylesheet?: CSSStyleSheet | null): Promise<void> {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-keytable-bs5')).default;
    } else {
        (await import('datatables.net-keytable-dt')).default;
    }
}