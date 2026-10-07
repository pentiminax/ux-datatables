import { describe, expect, it } from 'vitest'
import { textColumnRenderer } from '../textColumnRenderer.js'

function configuredRender(column: Record<string, any> = { type: 'string' }) {
    textColumnRenderer.configure(column)

    return column.render as (data: unknown, type: string) => unknown
}

describe('textColumnRenderer', () => {
    it('matches only the plain-text DataTables types', () => {
        expect(textColumnRenderer.matches({ type: 'string' })).toBe(true)
        expect(textColumnRenderer.matches({ type: 'string-utf8' })).toBe(true)
        expect(textColumnRenderer.matches({ type: 'html' })).toBe(false)
        expect(textColumnRenderer.matches({ type: 'html-utf8' })).toBe(false)
        expect(textColumnRenderer.matches({ type: 'num' })).toBe(false)
        expect(textColumnRenderer.matches({})).toBe(false)
    })

    it('escapes markup for display so a TextColumn cannot run stored HTML', () => {
        const render = configuredRender()

        expect(render('<img src=x onerror=alert(1)>', 'display')).toBe(
            '&lt;img src=x onerror=alert(1)&gt;'
        )
        expect(render('a & b', 'display')).toBe('a &amp; b')
        expect(render('"quoted"', 'display')).toBe('&quot;quoted&quot;')
    })

    it('keeps the raw value for sort, filter, and type', () => {
        const render = configuredRender()

        expect(render('<b>keep</b>', 'sort')).toBe('<b>keep</b>')
        expect(render('<b>keep</b>', 'filter')).toBe('<b>keep</b>')
        expect(render('<b>keep</b>', 'type')).toBe('<b>keep</b>')
    })

    it('leaves nullish display values for defaultContent', () => {
        const render = configuredRender()

        expect(render(null, 'display')).toBeNull()
        expect(render(undefined, 'display')).toBeUndefined()
    })

    it('does not replace an existing renderer', () => {
        const existing = (): string => 'already-configured'
        const column: Record<string, any> = { type: 'string', render: existing }

        textColumnRenderer.configure(column)

        expect(column.render).toBe(existing)
    })

    it.each([
        ['money', 'num', { isMoney: true }],
        ['switch', 'num', { renderAsSwitch: true }],
        ['choice', 'html', { choices: { draft: 'Draft' } }],
        ['email', 'html', { isEmail: true }],
        ['url', 'html', { isUrl: true }],
        ['icon', 'html', { isIcon: true }],
        ['image', 'html', { isImage: true }],
        ['plain number or boolean', 'num', {}],
    ])(
        'takes over a %s column formatted on the server and escapes it',
        (_name, type, options) => {
            const column: Record<string, any> = {
                type,
                customOptions: { ...options, formatted: true },
            }

            expect(textColumnRenderer.matches(column)).toBe(true)

            textColumnRenderer.configure(column)

            expect(column.render('<b>€ 12.50</b>', 'display')).toBe('&lt;b&gt;€ 12.50&lt;/b&gt;')
        }
    )

    it('leaves a typed column without a formatter to its own renderer', () => {
        expect(textColumnRenderer.matches({ type: 'num', customOptions: { isMoney: true } })).toBe(
            false
        )
    })

    it.each(['html', 'html-utf8', 'html-num', 'html-num-fmt'])(
        'leaves a formatted %s column that opted into html() to render its own markup',
        (type) => {
            expect(textColumnRenderer.matches({ type, customOptions: { formatted: true } })).toBe(
                false
            )
        }
    )
})
