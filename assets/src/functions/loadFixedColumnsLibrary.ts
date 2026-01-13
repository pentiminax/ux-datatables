export async function loadFixedColumnsLibrary(stylesheet?: CSSStyleSheet | null): Promise<any> {
    if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
        (await import('datatables.net-fixedcolumns-bs5')).default;
        (await import('datatables.net-fixedcolumns-bs5/css/fixedColumns.bootstrap5.min.css')).default;
    } else {
        (await import('datatables.net-fixedcolumns-dt')).default;
        (await import('datatables.net-fixedcolumns-dt/css/fixedColumns.dataTables.min.css')).default;
    }
}