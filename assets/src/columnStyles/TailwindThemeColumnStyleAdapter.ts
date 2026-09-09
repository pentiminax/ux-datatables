import { escapeHtml } from '../functions/htmlUtils.js'
import type { ColumnStyleAdapter, SwitchRenderOptions } from './ColumnStyleAdapter.js'

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
])

function modifier(prefix: string, variant: string): string {
    return VARIANTS.has(variant) ? `${prefix}--${variant}` : `${prefix}--secondary`
}

/**
 * Emits the semantic classes styled by `styles/datatables-theme-tailwind.css`.
 *
 * Unlike `TailwindColumnStyleAdapter`, none of these class names come from a
 * utility framework, so the chrome renders correctly in apps with no Tailwind
 * build step — AssetMapper installs in particular.
 */
export class TailwindThemeColumnStyleAdapter implements ColumnStyleAdapter {
    renderBadge(label: string, variant: string): string {
        return `<span class="dt-badge ${modifier('dt-badge', variant)}">${escapeHtml(label)}</span>`
    }

    renderIcon(iconSvg: string, variant: string, tooltip: string): string {
        const title = tooltip ? ` title="${escapeHtml(tooltip)}"` : ''

        return `<span class="dt-icon ${modifier('dt-icon', variant)}"${title}>${iconSvg}</span>`
    }

    renderSwitch(options: SwitchRenderOptions): string {
        const checked = options.checked ? ' checked' : ''
        const disabled = options.disabled ? ' disabled' : ''

        return (
            `<label class="dt-switch">` +
            `<input class="dt-switch__input boolean-switch-action" type="checkbox" role="switch"` +
            ` aria-label="${options.ariaLabel}"` +
            ` data-id="${options.dataId}"` +
            ` data-url="${options.dataUrl}"` +
            ` data-field="${options.dataField}"` +
            ` data-method="${options.dataMethod}"` +
            `${checked}${disabled}>` +
            `<span class="dt-switch__track"></span>` +
            `</label>`
        )
    }
}
