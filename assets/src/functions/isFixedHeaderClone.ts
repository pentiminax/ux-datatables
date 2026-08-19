// The DataTables FixedHeader extension builds its floating header/footer by cloning the live
// <table> node (cloneNode(false)) and appending the clone to <body>. The clone inherits every
// attribute except `id` — including `data-controller` — so Stimulus's MutationObserver would
// otherwise connect and initialize a second DataTable on it, which DataTables' core rejects
// ("Cannot reinitialise DataTable") because the real, tracked <thead> is reparented into the
// clone. FixedHeader marks the clone `aria-hidden="true"`, which a live, interactive table is
// never expected to carry, so it doubles as a reliable signal to skip connecting to it.
export function isFixedHeaderClone(element: Element): boolean {
    return element.getAttribute('aria-hidden') === 'true'
}
