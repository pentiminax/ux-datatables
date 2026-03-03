import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js';
export const urlColumnRenderer = {
    matches(column) {
        return (typeof column?.urlTemplate === 'string' ||
            typeof column?.urlTarget === 'string' ||
            typeof column?.urlDisplayValue === 'string' ||
            true === column?.urlShowExternalIcon);
    },
    configure(column) {
        const urlTemplate = column.urlTemplate;
        const routeParams = typeof column.urlRouteParams === 'object' ? column.urlRouteParams : null;
        const target = typeof column.urlTarget === 'string' ? column.urlTarget : null;
        const displayValue = typeof column.urlDisplayValue === 'string' ? column.urlDisplayValue : null;
        const showExternalIcon = true === column.urlShowExternalIcon;
        column.render = (data, type, row) => {
            if (type !== 'display') {
                return data;
            }
            let href;
            if (urlTemplate && routeParams) {
                href = urlTemplate;
                for (const [paramName, fieldName] of Object.entries(routeParams)) {
                    const value = row[fieldName] ?? '';
                    href = href.replace(`{${paramName}}`, encodeURIComponent(String(value)));
                }
            }
            else {
                href = typeof data === 'string' ? data : '';
            }
            if (isUnsafeUrl(href)) {
                return escapeHtml(String(data ?? ''));
            }
            const escapedHref = escapeHtml(href);
            const text = escapeHtml(displayValue ?? data ?? href);
            const attrs = [`href="${escapedHref}"`];
            if (target) {
                attrs.push(`target="${escapeHtml(target)}"`);
            }
            if (target === '_blank') {
                attrs.push('rel="noopener noreferrer"');
            }
            let html = `<a ${attrs.join(' ')}>${text}</a>`;
            if (showExternalIcon) {
                html += ' <span aria-label="external link">&#x2197;</span>';
            }
            return html;
        };
    },
};
//# sourceMappingURL=urlColumnRenderer.js.map