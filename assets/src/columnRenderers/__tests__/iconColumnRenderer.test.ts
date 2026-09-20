import { beforeAll, describe, expect, it } from 'vitest'
import type { ColumnStyleAdapter } from '../../columnStyles/ColumnStyleAdapter.js'
import { createIconColumnRenderer, loadLucideIcons } from '../iconColumnRenderer.js'

const style: ColumnStyleAdapter = {
    renderBadge: (label, variant) => `badge:${variant}:${label}`,
    renderSwitch: () => 'switch',
    renderIcon: (svg, variant, tooltip) => `icon[${variant}|${tooltip}]${svg}`,
}

function configuredRender(
    customOptions: Record<string, unknown>,
    column: Record<string, any> = {}
) {
    column.customOptions = customOptions
    createIconColumnRenderer(style).configure(column)
    return column.render as (data: any, type: string, row?: any) => any
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
        const render = configuredRender({ isIcon: true, icon: 'circle-check' })
        expect(render('active', 'sort')).toBe('active')
        expect(render('active', 'filter')).toBe('active')
    })

    it('renders the static icon and color', () => {
        const render = configuredRender({
            isIcon: true,
            icon: 'circle-check',
            color: 'success',
            tooltips: { active: 'Active account' },
        })
        const html = render('active', 'display')

        expect(html).toContain('icon[success|Active account]')
        expect(html).toContain('<svg')
    })

    it('lets dynamic icon/color from the row win over the static ones', () => {
        const render = configuredRender(
            { isIcon: true, icon: 'circle-check', color: 'success' },
            { data: 'status' }
        )
        const html = render('draft', 'display', {
            __ux_datatables_icons: { status: { icon: 'circle-x', color: 'danger' } },
        })

        expect(html).toContain('icon[danger|]')
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

    it('resolves acronym icons whose kebab value has adjacent capitals', () => {
        const render = configuredRender({ isIcon: true, icon: 'arrow-down-az' })
        const html = render('active', 'display')

        expect(html).toContain('<svg')
    })

    it('returns an empty string for an unknown icon name', () => {
        const render = configuredRender({
            isIcon: true,
            icon: 'this-icon-does-not-exist-xyz',
        })
        expect(render('active', 'display')).toBe('')
    })

    it('applies the size to the svg dimensions', () => {
        const render = configuredRender({
            isIcon: true,
            icon: 'circle-check',
            size: 'lg',
        })
        const html = render('active', 'display')

        expect(html).toContain('width="24"')
        expect(html).toContain('height="24"')
    })
})
