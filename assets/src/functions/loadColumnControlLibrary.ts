export async function loadColumnControlLibrary(stylesheet?: CSSStyleSheet | null): Promise<void> {
  if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
    ;(await import('datatables.net-columncontrol-bs5')).default
  } else {
    ;(await import('datatables.net-columncontrol-dt')).default
  }
}
