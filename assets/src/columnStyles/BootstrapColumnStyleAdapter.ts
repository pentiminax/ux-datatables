import { escapeHtml } from '../functions/htmlUtils.js'
import type { ColumnStyleAdapter, SwitchRenderOptions } from './ColumnStyleAdapter.js'

export class BootstrapColumnStyleAdapter implements ColumnStyleAdapter {
    renderBadge(label: string, variant: string): string {
        const escapedLabel = escapeHtml(label)
        const escapedVariant = escapeHtml(variant)

        return `<span class="badge text-bg-${escapedVariant}">${escapedLabel}</span>`
    }

    renderSwitch(options: SwitchRenderOptions): string {
        const checked = options.checked ? ' checked' : ''
        const disabled = options.disabled ? ' disabled' : ''

        return (
            `<div class="form-check form-switch m-0">` +
            `<input class="form-check-input boolean-switch-action" type="checkbox" role="switch"` +
            ` aria-label="${options.ariaLabel}"` +
            ` data-id="${options.dataId}"` +
            ` data-url="${options.dataUrl}"` +
            ` data-field="${options.dataField}"` +
            ` data-method="${options.dataMethod}"` +
            `${checked}${disabled}>` +
            `</div>`
        )
    }
}
