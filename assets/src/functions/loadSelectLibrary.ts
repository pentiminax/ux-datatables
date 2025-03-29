export async function loadSelectLibrary(stylesheet?: CSSStyleSheet) {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        return (await import('datatables.net-select-bs5')).default;
    } else {
        return (await import('datatables.net-select-dt')).default;
    }
}