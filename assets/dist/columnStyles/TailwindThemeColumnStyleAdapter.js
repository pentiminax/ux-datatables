import { escapeHtml } from '../functions/htmlUtils.js';
const VARIANTS = new Set([
    'success',
    'warning',
    'danger',
    'info',
    'primary',
    'secondary',
    'light',
    'dark',
    'gray',
]);
function modifier(prefix, variant) {
    return VARIANTS.has(variant) ? `${prefix}--${variant}` : `${prefix}--secondary`;
}
export class TailwindThemeColumnStyleAdapter {
    renderBadge(label, variant) {
        return `<span class="dt-badge ${modifier('dt-badge', variant)}">${escapeHtml(label)}</span>`;
    }
    renderIcon(iconSvg, variant, tooltip) {
        const title = tooltip ? ` title="${escapeHtml(tooltip)}"` : '';
        return `<span class="dt-icon ${modifier('dt-icon', variant)}"${title}>${iconSvg}</span>`;
    }
    renderSwitch(options) {
        const checked = options.checked ? ' checked' : '';
        const disabled = options.disabled ? ' disabled' : '';
        return (`<label class="dt-switch">` +
            `<input class="dt-switch__input boolean-switch-action" type="checkbox" role="switch"` +
            ` aria-label="${options.ariaLabel}"` +
            ` data-id="${options.dataId}"` +
            ` data-url="${options.dataUrl}"` +
            ` data-field="${options.dataField}"` +
            ` data-method="${options.dataMethod}"` +
            `${checked}${disabled}>` +
            `<span class="dt-switch__track"></span>` +
            `</label>`);
    }
}
//# sourceMappingURL=TailwindThemeColumnStyleAdapter.js.map