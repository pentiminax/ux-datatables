import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { relativeDateColumnRenderer } from '../relativeDateColumnRenderer.js'

const NOW = new Date('2026-09-10T12:00:00Z')

function configuredRender(customOptions: Record<string, unknown> = { relative: true }) {
    const column: Record<string, any> = { customOptions }
    relativeDateColumnRenderer.configure(column)

    return column.render as (data: any, type: string) => any
}

describe('relativeDateColumnRenderer', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        vi.setSystemTime(NOW)
        document.documentElement.lang = 'en-GB'
    })

    afterEach(() => {
        vi.useRealTimers()
        document.documentElement.lang = ''
    })

    it('matches only relative date columns', () => {
        expect(relativeDateColumnRenderer.matches({ customOptions: { relative: true } })).toBe(true)
        expect(relativeDateColumnRenderer.matches({ customOptions: { dateFormat: 'Y-m-d' } })).toBe(
            false
        )
        expect(relativeDateColumnRenderer.matches({})).toBe(false)
    })

    it('returns the raw ISO value for non-display types so ordering and search stay on the date', () => {
        const render = configuredRender()

        expect(render('2026-09-10T11:55:00+00:00', 'sort')).toBe('2026-09-10T11:55:00+00:00')
        expect(render('2026-09-10T11:55:00+00:00', 'type')).toBe('2026-09-10T11:55:00+00:00')
        expect(render('2026-09-10T11:55:00+00:00', 'filter')).toBe('2026-09-10T11:55:00+00:00')
    })

    it('renders an empty cell for missing values', () => {
        const render = configuredRender()

        expect(render(null, 'display')).toBe('')
        expect(render(undefined, 'display')).toBe('')
        expect(render('', 'display')).toBe('')
    })

    it('renders past dates as relative labels', () => {
        const render = configuredRender()

        expect(render('2026-09-10T11:59:40+00:00', 'display')).toBe('20 seconds ago')
        expect(render('2026-09-10T11:55:00+00:00', 'display')).toBe('5 minutes ago')
        expect(render('2026-09-10T09:00:00+00:00', 'display')).toBe('3 hours ago')
        expect(render('2026-09-09T11:00:00+00:00', 'display')).toBe('yesterday')
        expect(render('2026-09-08T12:00:00+00:00', 'display')).toBe('2 days ago')
    })

    it('renders future dates without flipping the sign', () => {
        const render = configuredRender()

        expect(render('2026-09-10T12:05:00+00:00', 'display')).toBe('in 5 minutes')
    })

    it('escapes and returns the raw value when the date cannot be parsed', () => {
        const render = configuredRender()

        expect(render('<b>nope</b>', 'display')).toBe('&lt;b&gt;nope&lt;/b&gt;')
    })

    it('prefers an explicit locale over the document language', () => {
        const render = configuredRender({ relative: true, locale: 'fr-FR' })

        expect(render('2026-09-10T11:55:00+00:00', 'display')).toBe('il y a 5 minutes')
    })
})
