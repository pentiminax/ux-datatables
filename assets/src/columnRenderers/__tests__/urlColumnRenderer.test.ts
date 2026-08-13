import { describe, expect, it } from 'vitest'
import { urlColumnRenderer } from '../urlColumnRenderer.js'

function configuredRender(
    customOptions: Record<string, unknown>,
    column: Record<string, any> = {}
) {
    column.customOptions = customOptions
    urlColumnRenderer.configure(column)
    const render = column.render as (data: any, type: string, row: any) => any
    return (data: any, type: string, row: Record<string, any> = {}) => render(data, type, row)
}

describe('urlColumnRenderer', () => {
    it('matches columns exposing any url-related custom option', () => {
        expect(urlColumnRenderer.matches({ customOptions: { isUrl: true } })).toBe(true)
        expect(urlColumnRenderer.matches({ customOptions: { target: '_blank' } })).toBe(true)
        expect(urlColumnRenderer.matches({ customOptions: { isImage: true } })).toBe(false)
        expect(urlColumnRenderer.matches({})).toBe(false)
    })

    it('returns the raw value for non-display types', () => {
        const render = configuredRender({ isUrl: true })
        expect(render('https://example.com', 'sort')).toBe('https://example.com')
        expect(render('https://example.com', 'filter')).toBe('https://example.com')
    })

    it('renders an anchor for a non-empty href', () => {
        const render = configuredRender({ isUrl: true })
        expect(render('https://example.com', 'display')).toBe(
            '<a href="https://example.com">https://example.com</a>'
        )
    })

    it('renders plain escaped text instead of an empty anchor when the href is empty', () => {
        const render = configuredRender({ isUrl: true })
        expect(render('', 'display')).toBe('')
        expect(render(null, 'display')).toBe('')
    })

    it('falls back to displayValue as plain text when the href is empty', () => {
        const render = configuredRender({ isUrl: true, displayValue: 'No link available' })
        expect(render('', 'display')).toBe('No link available')
    })

    it('treats a resolved-but-empty row url the same as an empty cell value', () => {
        const render = configuredRender({ isUrl: true }, { data: 'website' })
        const html = render('', 'display', { __ux_datatables_urls: { website: '' } })
        expect(html).toBe('')
    })

    it('still renders an anchor for an empty href when renderEmptyAsAnchor is true', () => {
        const render = configuredRender({ isUrl: true, renderEmptyAsAnchor: true })
        expect(render('', 'display')).toBe('<a href=""></a>')
    })

    it('renders external icon and target attributes for a non-empty href', () => {
        const render = configuredRender({
            isUrl: true,
            target: '_blank',
            showExternalIcon: true,
        })
        const html = render('https://example.com', 'display')

        expect(html).toContain('target="_blank"')
        expect(html).toContain('rel="noopener noreferrer"')
        expect(html).toContain('aria-label="external link"')
    })

    it('escapes an unsafe url as plain text regardless of emptiness handling', () => {
        const render = configuredRender({ isUrl: true })
        expect(render('javascript:alert(1)', 'display')).toBe('javascript:alert(1)')
    })
})
