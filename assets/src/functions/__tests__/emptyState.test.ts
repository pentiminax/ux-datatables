import { describe, expect, it, vi } from 'vitest'
import {
    applyEmptyState,
    bindEmptyStateReset,
    buildEmptyHtml,
    buildNoResultsHtml,
} from '../emptyState.js'

const XSS = '<img src=x onerror=alert(1)>'

describe('empty state HTML', () => {
    it('escapes the heading, description and action label', () => {
        const html = buildEmptyHtml({
            heading: XSS,
            description: `"${XSS}"`,
            action: { label: XSS, url: '/new' },
        })

        expect(html).not.toContain('<img')
        expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;')
        expect(html).toContain('href="/new"')
    })

    it('escapes the action URL attribute', () => {
        const html = buildEmptyHtml({
            heading: 'h',
            action: { label: 'Go', url: '/a"onmouseover="alert(1)' },
        })

        expect(html).not.toContain('"onmouseover')
    })

    it.each(['javascript:alert(1)', 'data:text/html,x', '//evil.example', 'vbscript:x'])(
        'drops the action for the unsafe URL %s',
        (url) => {
            const html = buildEmptyHtml({ heading: 'h', action: { label: 'Go', url } })

            expect(html).not.toContain('<a')
        }
    )

    it('offers a reset button only when filters exist', () => {
        const config = { heading: 'h', labels: { resetFilters: '<b>Reset</b>' } }

        expect(buildNoResultsHtml(config, false)).not.toContain('data-dt-empty-reset')
        expect(buildNoResultsHtml(config, true)).toContain('data-dt-empty-reset')
        expect(buildNoResultsHtml(config, true)).toContain('&lt;b&gt;Reset&lt;/b&gt;')
    })

    it('falls back to built-in English texts', () => {
        expect(buildNoResultsHtml({ heading: 'h' }, true)).toContain('No results found')
    })
})

describe('applyEmptyState', () => {
    it('sets both language strings and keeps the rest of the catalog', () => {
        const payload: Record<string, any> = {
            language: { search: 'Find' },
            emptyState: { heading: 'Nothing here' },
            filters: [{ name: 'a' }],
        }

        applyEmptyState(payload)

        expect(payload.language.search).toBe('Find')
        expect(payload.language.emptyTable).toContain('Nothing here')
        expect(payload.language.zeroRecords).toContain('data-dt-empty-reset')
    })

    it('leaves a table without an empty state untouched', () => {
        const payload: Record<string, any> = { language: { emptyTable: 'x' } }

        applyEmptyState(payload)

        expect(payload.language).toEqual({ emptyTable: 'x' })
    })
})

describe('bindEmptyStateReset', () => {
    it('calls reset when the reset button is clicked', () => {
        const table = document.createElement('table')
        table.innerHTML =
            '<tbody><tr><td><button data-dt-empty-reset><i></i></button></td></tr></tbody>'
        const reset = vi.fn()
        bindEmptyStateReset(table, reset)

        table.querySelector('i')?.dispatchEvent(new MouseEvent('click', { bubbles: true }))

        expect(reset).toHaveBeenCalledOnce()
    })
})
