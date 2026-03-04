import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js';
export const urlColumnRenderer = {
    matches(column) {
        const opts = column?.customOptions;
        return (typeof opts?.template === 'string' ||
            typeof opts?.target === 'string' ||
            typeof opts?.displayValue === 'string' ||
            true === opts?.showExternalIcon);
    },
    configure(column) {
        const customOptions = (column.customOptions ?? {});
        const urlTemplate = customOptions.template;
        const routeParams = typeof customOptions.routeParams === 'object' ? customOptions.routeParams : null;
        const target = typeof customOptions.target === 'string' ? customOptions.target : null;
        const displayValue = typeof customOptions.displayValue === 'string' ? customOptions.displayValue : null;
        const showExternalIcon = true === customOptions.showExternalIcon;
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