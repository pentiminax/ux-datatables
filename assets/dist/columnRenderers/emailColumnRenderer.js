import { escapeHtml } from '../functions/htmlUtils.js';
function maskEmail(email) {
    const atIndex = email.indexOf('@');
    if (atIndex <= 0) {
        return email;
    }
    return email[0] + '***' + email.slice(atIndex);
}
function obfuscateMailto(email) {
    return email.replace(/@/g, '&#64;').replace(/\./g, '&#46;');
}
export const emailColumnRenderer = {
    matches(column) {
        return true === column?.customOptions?.isEmail;
    },
    configure(column) {
        const customOptions = (column.customOptions ?? {});
        const shouldObfuscate = true === customOptions.obfuscate;
        const shouldMask = true === customOptions.mask;
        const displayValue = typeof customOptions.displayValue === 'string' ? customOptions.displayValue : null;
        column.render = (data, type, _row) => {
            if (type !== 'display') {
                return data;
            }
            const email = typeof data === 'string' ? data : '';
            if (!email) {
                return '';
            }
            const href = shouldObfuscate
                ? `mailto:${obfuscateMailto(email)}`
                : `mailto:${escapeHtml(email)}`;
            let text;
            if (displayValue !== null) {
                text = escapeHtml(displayValue);
            }
            else if (shouldMask) {
                text = escapeHtml(maskEmail(email));
            }
            else {
                text = escapeHtml(email);
            }
            return `<a href="${href}">${text}</a>`;
        };
    },
};
//# sourceMappingURL=emailColumnRenderer.js.map