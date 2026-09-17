import { describe, expect, it } from 'vitest'
import { TailwindThemeColumnStyleAdapter } from '../TailwindThemeColumnStyleAdapter.js'

describe('TailwindThemeColumnStyleAdapter', () => {
    const adapter = new TailwindThemeColumnStyleAdapter()

    it.each([
        'success',
        'warning',
        'danger',
        'info',
        'primary',
        'secondary',
        'light',
        'dark',
    ] as const)('renders the %s badge modifier', (variant) => {
        expect(adapter.renderBadge('Active', variant)).toBe(
            `<span class="dt-badge dt-badge--${variant}">Active</span>`
        )
    })

    it('falls back to the secondary badge for an unknown variant', () => {
        expect(adapter.renderBadge('Draft', 'chartreuse')).toContain('dt-badge--secondary')
    })

    it('escapes the badge label', () => {
        expect(adapter.renderBadge('<script>', 'info')).toBe(
            '<span class="dt-badge dt-badge--info">&lt;script&gt;</span>'
        )
    })

    it('emits no utility classes, so the chrome works without a Tailwind build', () => {
        const markup = [
            adapter.renderBadge('Active', 'success'),
            adapter.renderIcon('<svg />', 'danger', ''),
            adapter.renderSwitch({
                checked: true,
                disabled: false,
                ariaLabel: 'Toggle',
                dataId: '1',
                dataUrl: '/toggle',
                dataField: 'active',
                dataMethod: 'PATCH',
            }),
        ].join('')

        expect(markup).not.toMatch(/\b(bg|text|ring|peer)-[a-z]+-\d/)
    })

    it('renders an icon with a tooltip', () => {
        expect(adapter.renderIcon('<svg />', 'warning', 'Pending review')).toBe(
            '<span class="dt-icon dt-icon--warning" title="Pending review"><svg /></span>'
        )
    })

    it('omits the title attribute when no tooltip is given', () => {
        expect(adapter.renderIcon('<svg />', 'gray', '')).toBe(
            '<span class="dt-icon dt-icon--gray"><svg /></span>'
        )
    })

    it('renders a checked switch carrying the mutation data attributes', () => {
        const markup = adapter.renderSwitch({
            checked: true,
            disabled: false,
            ariaLabel: 'Toggle active',
            dataId: '42',
            dataUrl: '/table/toggle',
            dataField: 'active',
            dataMethod: 'PATCH',
        })

        expect(markup).toContain('class="dt-switch"')
        expect(markup).toContain('boolean-switch-action')
        expect(markup).toContain('role="switch"')
        expect(markup).toContain('aria-label="Toggle active"')
        expect(markup).toContain('data-id="42"')
        expect(markup).toContain('data-url="/table/toggle"')
        expect(markup).toContain('data-field="active"')
        expect(markup).toContain('data-method="PATCH"')
        expect(markup).toContain(' checked>')
        expect(markup).not.toContain(' disabled')
    })

    it('renders a disabled, unchecked switch', () => {
        const markup = adapter.renderSwitch({
            checked: false,
            disabled: true,
            ariaLabel: 'Toggle active',
            dataId: '42',
            dataUrl: '/table/toggle',
            dataField: 'active',
            dataMethod: 'PATCH',
        })

        expect(markup).toContain(' disabled>')
        expect(markup).not.toContain(' checked')
    })
})
