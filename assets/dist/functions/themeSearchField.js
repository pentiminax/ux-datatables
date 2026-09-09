export function applyThemeSearchPlaceholder(container) {
    for (const search of container.querySelectorAll('div.dt-search')) {
        const label = search.querySelector('label');
        const input = search.querySelector('input');
        if (!label || !input) {
            continue;
        }
        if (!input.placeholder) {
            const text = (label.textContent ?? '').trim().replace(/\s*:$/, '');
            if (text !== '') {
                input.placeholder = `${text}…`;
            }
        }
        label.classList.add('dt-search-label-hidden');
    }
}
//# sourceMappingURL=themeSearchField.js.map