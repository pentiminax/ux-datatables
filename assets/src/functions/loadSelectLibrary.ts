export async function loadSelectLibrary(stylesheet?: CSSStyleSheet | null): Promise<void> {
  if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
    ;(await import('datatables.net-select-bs5')).default
  } else {
    ;(await import('datatables.net-select-dt')).default
  }
}
