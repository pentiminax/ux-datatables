import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js'
import type { ColumnRenderer, UrlCustomOptions } from './types.js'

export const urlColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        const opts = column?.customOptions
        return (
            typeof opts?.template === 'string' ||
            typeof opts?.target === 'string' ||
            typeof opts?.displayValue === 'string' ||
            true === opts?.showExternalIcon
        )
    },

    configure(column: Record<string, any>): void {
        const customOptions = (column.customOptions ?? {}) as UrlCustomOptions
        const urlTemplate = customOptions.template
        const routeParams =
            typeof customOptions.routeParams === 'object' ? customOptions.routeParams : null
        const target = typeof customOptions.target === 'string' ? customOptions.target : null
        const displayValue =
            typeof customOptions.displayValue === 'string' ? customOptions.displayValue : null
        const showExternalIcon = true === customOptions.showExternalIcon

        column.render = (data: any, type: string, row: Record<string, any>): any => {
            if (type !== 'display') {
                return data
            }

            let href: string
            if (urlTemplate && routeParams) {
                href = urlTemplate
                for (const [paramName, fieldName] of Object.entries(routeParams)) {
                    const value = row[fieldName as string] ?? ''
                    href = href.replace(`{${paramName}}`, encodeURIComponent(String(value)))
                }
            } else {
                href = typeof data === 'string' ? data : ''
            }

            if (isUnsafeUrl(href)) {
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
