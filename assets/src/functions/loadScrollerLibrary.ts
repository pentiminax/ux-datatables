export async function loadScrollerLibrary(stylesheet?: CSSStyleSheet | null): Promise<void> {
  if (stylesheet?.href?.includes('dataTables.bootstrap5')) {
    ;(await import('datatables.net-scroller-bs5')).default
    ;(await import('datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css')).default
  } else {
    ;(await import('datatables.net-scroller-dt')).default
    ;(await import('datatables.net-scroller-dt/css/scroller.dataTables.min.css')).default
  }
}
