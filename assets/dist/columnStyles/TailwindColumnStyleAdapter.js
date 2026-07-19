import { escapeHtml } from '../functions/htmlUtils.js';
const BADGE_BASE_CLASSES = 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium';
const BADGE_VARIANT_CLASSES = {
    success: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-sky-100 text-sky-800',
    primary: 'bg-blue-100 text-blue-800',
    secondary: 'bg-gray-100 text-gray-800',
    light: 'bg-gray-50 text-gray-700',
    dark: 'bg-gray-800 text-gray-100',
};
const SWITCH_TRACK_CLASSES = 'relative h-6 w-11 rounded-full bg-gray-200 after:absolute after:start-[2px] after:top-[2px] ' +
    'after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white ' +
    "after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full " +
    'peer-checked:after:border-white peer-disabled:cursor-not-allowed peer-disabled:opacity-50';
export class TailwindColumnStyleAdapter {
    renderBadge(label, variant) {
        const escapedLabel = escapeHtml(label);
        const variantClasses = BADGE_VARIANT_CLASSES[variant] ?? BADGE_VARIANT_CLASSES.secondary;
        return `<span class="${BADGE_BASE_CLASSES} ${variantClasses}">${escapedLabel}</span>`;
    }
    renderSwitch(options) {
        const checked = options.checked ? ' checked' : '';
        const disabled = options.disabled ? ' disabled' : '';
        return (`<label class="relative m-0 inline-flex cursor-pointer items-center">` +
            `<input class="peer sr-only boolean-switch-action" type="checkbox" role="switch"` +
            ` aria-label="${options.ariaLabel}"` +
            ` data-id="${options.dataId}"` +
            ` data-url="${options.dataUrl}"` +
            ` data-field="${options.dataField}"` +
            ` data-method="${options.dataMethod}"` +
            `${checked}${disabled}>` +
            `<div class="${SWITCH_TRACK_CLASSES}"></div>` +
            `</label>`);
    }
}
//# sourceMappingURL=TailwindColumnStyleAdapter.js.map