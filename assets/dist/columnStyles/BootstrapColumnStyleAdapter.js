import { escapeHtml } from '../functions/htmlUtils.js';
export class BootstrapColumnStyleAdapter {
    renderBadge(label, variant) {
        const escapedLabel = escapeHtml(label);
        const escapedVariant = escapeHtml(variant);
        return `<span class="badge text-bg-${escapedVariant}">${escapedLabel}</span>`;
    }
    renderIcon(iconSvg, variant, tooltip) {
        const colorClass = variant ? ` text-${escapeHtml(variant)}` : '';
        const title = tooltip ? ` title="${escapeHtml(tooltip)}"` : '';
        return `<span class="ux-datatables-icon${colorClass}"${title}>${iconSvg}</span>`;
    }
    renderSwitch(options) {
        const checked = options.checked ? ' checked' : '';
        const disabled = options.disabled ? ' disabled' : '';
        return (`<div class="form-check form-switch m-0">` +
            `<input class="form-check-input boolean-switch-action" type="checkbox" role="switch"` +
            ` aria-label="${options.ariaLabel}"` +
            ` data-id="${options.dataId}"` +
            ` data-url="${options.dataUrl}"` +
            ` data-field="${options.dataField}"` +
            ` data-method="${options.dataMethod}"` +
            `${checked}${disabled}>` +
            `</div>`);
    }
}
//# sourceMappingURL=BootstrapColumnStyleAdapter.js.map