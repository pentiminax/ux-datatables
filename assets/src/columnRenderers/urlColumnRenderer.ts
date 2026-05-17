import {
    escapeHtml,
    isAllowedUrlProtocol,
    isUnsafeUrl,
    withDefaultProtocol,
} from '../functions/htmlUtils.js'
import type { ColumnRenderer, UrlCustomOptions, UrlRowData } from './types.js'

export const urlColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        const opts = (column?.customOptions ?? {}) as UrlCustomOptions

        return (
            true === opts.isUrl ||
            opts.target !== undefined ||
            opts.displayValue !== undefined ||
            true === opts.showExternalIcon ||
            opts.defaultProtocol !== undefined ||
            opts.allowedProtocols !== undefined
        )
    },

    configure(column: Record<string, any>): void {
        const { target, displayValue, showExternalIcon, defaultProtocol, allowedProtocols } =
            (column.customOptions ?? {}) as UrlCustomOptions

        column.render = (data: any, type: string, row: Record<string, any> & UrlRowData): any => {
            if (type !== 'display') {
                return data
            }

            const key = column.data ?? column.name
            const rawHref =
                typeof key === 'string' && row.__ux_datatables_urls?.[key]
                    ? row.__ux_datatables_urls[key]
                    : typeof data === 'string'
                      ? data
                      : ''
            const href = withDefaultProtocol(rawHref, defaultProtocol)

            if (isUnsafeUrl(href) || !isAllowedUrlProtocol(href, allowedProtocols)) {
                return escapeHtml(String(data ?? ''))
            }

            const escapedHref = escapeHtml(href)
            const text = escapeHtml(displayValue ?? data ?? href)

            const attrs: string[] = [`href="${escapedHref}"`]

            if (target) {
                attrs.push(`target="${escapeHtml(target)}"`)
            }

            if (target === '_blank') {
                attrs.push('rel="noopener noreferrer"')
            }

            let html = `<a ${attrs.join(' ')}>${text}</a>`

            if (showExternalIcon) {
                html += ' <span aria-label="external link">&#x2197;</span>'
            }

            return html
        }
    },
}
