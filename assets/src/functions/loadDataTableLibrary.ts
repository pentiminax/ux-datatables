export async function loadDataTableLibrary(stylesheet?: CSSStyleSheet | null): Promise<any> {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        return (await import('datatables.net-bs5')).default;
    } else {
        return (await import('datatables.net-dt')).default;
    }
}