import { beforeAll, describe, expect, it } from 'vitest'
import type { ColumnStyleAdapter } from '../../columnStyles/ColumnStyleAdapter.js'
import { createIconColumnRenderer, loadLucideIcons } from '../iconColumnRenderer.js'

const style: ColumnStyleAdapter = {
    renderBadge: (label, variant) => `badge:${variant}:${label}`,
    renderSwitch: () => 'switch',
    renderIcon: (svg, variant, tooltip) => `icon[${variant}|${tooltip}]${svg}`,
}

function configuredRender(customOptions: Record<string, unknown>) {
    const column: Record<string, any> = { customOptions }
    createIconColumnRenderer(style).configure(column)
    return column.render as (data: any, type: string) => any
}

describe('iconColumnRenderer', () => {
    beforeAll(async () => {
        await loadLucideIcons()
    })

    it('matches only icon columns', () => {
        const renderer = createIconColumnRenderer(style)
        expect(renderer.matches({ customOptions: { isIcon: true } })).toBe(true)
        expect(renderer.matches({ customOptions: { isImage: true } })).toBe(false)
        expect(renderer.matches({})).toBe(false)
    })

    it('returns the raw value for non-display types', () => {
        const render = configuredRender({ isIcon: true, icons: { active: 'circle-check' } })
        expect(render('active', 'sort')).toBe('active')
        expect(render('active', 'filter')).toBe('active')
    })

    it('resolves an icon and color from the maps', () => {
        const render = configuredRender({
            isIcon: true,
            icons: { active: 'circle-check' },
            colors: { active: 'success' },
            tooltips: { active: 'Active account' },
        })
        const html = render('active', 'display')

        expect(html).toContain('icon[success|Active account]')
        expect(html).toContain('<svg')
    })

    it('falls back to the default icon/color for unmapped values', () => {
        const render = configuredRender({
            isIcon: true,
            icons: { active: 'circle-check' },
            defaultIcon: 'circle',
            defaultColor: 'secondary',
        })
        const html = render('unknown', 'display')

        expect(html).toContain('icon[secondary|]')
        expect(html).toContain('<svg')
    })

    it('renders the boolean true/false icons', () => {
        const render = configuredRender({
            isIcon: true,
            boolean: true,
            trueIcon: 'circle-check',
            falseIcon: 'circle-x',
            trueColor: 'success',
            falseColor: 'danger',
        })

        expect(render(true, 'display')).toContain('icon[success|]')
        expect(render(false, 'display')).toContain('icon[danger|]')
    })

    it('returns an empty string for an unknown icon name', () => {
        const render = configuredRender({
            isIcon: true,
            icons: { active: 'this-icon-does-not-exist-xyz' },
        })
        expect(render('active', 'display')).toBe('')
    })

    it('applies the size to the svg dimensions', () => {
        const render = configuredRender({
            isIcon: true,
            icons: { active: 'circle-check' },
            size: 'lg',
        })
        const html = render('active', 'display')

        expect(html).toContain('width="24"')
        expect(html).toContain('height="24"')
    })
})
