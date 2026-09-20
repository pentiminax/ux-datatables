// Several DataTables extensions clone the live <table> node wholesale to build a floating
// visual copy, and the clone inherits every attribute except `id` - including `data-controller`.
// Left unguarded, Stimulus's MutationObserver would connect and initialize a second DataTable on
// the clone, which DataTables' core rejects ("Cannot reinitialise DataTable") because the real,
// tracked <thead> is reparented into it - or, if the clone is still being manipulated by the
// extension that built it (e.g. mid-drag), the second, concurrent initialization races the first
// and corrupts internal state instead of cleanly erroring.
//
// FixedHeader marks its clone `aria-hidden="true"`, which a live, interactive table is never
// expected to carry, so it doubles as a reliable signal to skip connecting to it.
//
// ColReorder's floating drag-indicator table (built and torn down live during a column drag, not
// just once at init like FixedHeader's) carries no such attribute - it's identified instead by its
// own `dtcr-cloned` class. Without checking for it too, a live ColReorder drag - independent of
// whether FixedHeader is even in use - triggers a real, concurrent second DataTable construction
// on the drag clone, producing internal `TypeError`s deep in DataTables' own code (column-state
// lookups keyed by an index that doesn't exist as far as the clone's half-formed second instance
// is concerned) and visibly broken/duplicated drag rendering.
export function isDataTableClone(element: Element): boolean {
    return (
        element.getAttribute('aria-hidden') === 'true' || element.classList.contains('dtcr-cloned')
    )
}
