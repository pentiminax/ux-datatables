import { escapeHtml, isUnsafeUrl } from '../functions/htmlUtils.js'
import type { ColumnRenderer, ImageCustomOptions } from './types.js'

export function bindImagePlaceholder(root: HTMLElement): void {
    // error does not bubble, so listen in the capture phase
    root.addEventListener(
        'error',
        (e: Event): void => {
            const img = e.target
            if (!(img instanceof HTMLImageElement) || !img.dataset.placeholder) {
                return
            }

            const placeholder = img.dataset.placeholder
            delete img.dataset.placeholder
            img.src = placeholder
        },
        true
    )
}

export const imageColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return true === column?.customOptions?.isImage
    },

    configure(column: Record<string, any>): void {
        const customOptions = (column.customOptions ?? {}) as ImageCustomOptions
        const imageWidth =
            typeof customOptions.imageWidth === 'number' ? customOptions.imageWidth : null
        const imageHeight =
            typeof customOptions.imageHeight === 'number' ? customOptions.imageHeight : null
        const alt = typeof customOptions.alt === 'string' ? customOptions.alt : ''
        const lazy = !(customOptions.lazy === false)
        const rounded = true === customOptions.rounded
        const placeholder =
            typeof customOptions.placeholder === 'string' ? customOptions.placeholder : null
        const clickable = true === customOptions.clickable

        column.render = (data: any, type: string): any => {
            if (type !== 'display') {
                return data
            }

            const rawSrc = typeof data === 'string' && data.length > 0 ? data : null
            const src = rawSrc ?? placeholder ?? ''

            if (src.length === 0) {
                return ''
            }

            if (isUnsafeUrl(src)) {
                return escapeHtml(String(data ?? ''))
            }

            const escapedSrc = escapeHtml(src)
            const escapedAlt = escapeHtml(alt)

            const attrs: string[] = [`src="${escapedSrc}"`, `alt="${escapedAlt}"`]

            if (imageWidth !== null) {
                attrs.push(`width="${imageWidth}"`)
            }

            if (imageHeight !== null) {
                attrs.push(`height="${imageHeight}"`)
            }

            if (lazy) {
                attrs.push('loading="lazy"')
            }

            if (rounded) {
                attrs.push('class="rounded-circle rounded-full"')
            }

            if (placeholder !== null && rawSrc !== null) {
                attrs.push(`data-placeholder="${escapeHtml(placeholder)}"`)
            }

            const img = `<img ${attrs.join(' ')}>`

            if (clickable) {
                return `<a href="${escapedSrc}" target="_blank" rel="noopener noreferrer">${img}</a>`
            }

            return img
        }
    },
}
