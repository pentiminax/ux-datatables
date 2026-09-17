/**
 * Turns the DataTables search label into the input's placeholder.
 *
 * The theme lays the toolbar out as a single row, where a leading `Search:`
 * label costs horizontal space and reads as a second control. DataTables has no
 * option that moves that text, and its value can come from a remote language
 * file, so the label is read back from the DOM once the table has rendered.
 *
 * The label element itself stays in place, visually hidden, so the input keeps
 * its accessible name.
 */
export function applyThemeSearchPlaceholder(container: ParentNode): void {
    for (const search of container.querySelectorAll('div.dt-search')) {
        const label = search.querySelector('label')
        const input = search.querySelector('input')

        if (!label || !input) {
            continue
        }

        if (!input.placeholder) {
            const text = (label.textContent ?? '').trim().replace(/\s*:$/, '')

            if (text !== '') {
                input.placeholder = `${text}…`
            }
        }

        label.classList.add('dt-search-label-hidden')
    }
}
