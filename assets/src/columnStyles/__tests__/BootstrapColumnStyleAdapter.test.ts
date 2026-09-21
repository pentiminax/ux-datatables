import { describe, expect, it } from 'vitest'
import { BootstrapColumnStyleAdapter } from '../BootstrapColumnStyleAdapter.js'

describe('BootstrapColumnStyleAdapter', () => {
    const adapter = new BootstrapColumnStyleAdapter()

    it.each([
        ['success', 'badge text-bg-success'],
        ['warning', 'badge text-bg-warning'],
        ['danger', 'badge text-bg-danger'],
        ['info', 'badge text-bg-info'],
        ['primary', 'badge text-bg-primary'],
        ['secondary', 'badge text-bg-secondary'],
        ['light', 'badge text-bg-light'],
        ['dark', 'badge text-bg-dark'],
    ] as const)('renders a %s badge', (variant, className) => {
        expect(adapter.renderBadge('Label', variant)).toBe(
            `<span class="${className}">Label</span>`
        )
    })

    it('escapes badge label and variant', () => {
        expect(adapter.renderBadge('<b>X</b>', 'success" onclick="alert(1)')).toBe(
            '<span class="badge text-bg-success&quot; onclick=&quot;alert(1)">&lt;b&gt;X&lt;/b&gt;</span>'
        )
    })

    it('renders an icon with a color class and tooltip', () => {
        expect(adapter.renderIcon('<svg></svg>', 'success', 'Active')).toBe(
            '<span class="ux-datatables-icon text-success" title="Active"><svg></svg></span>'
        )
    })

    it('renders an icon without color or tooltip', () => {
        expect(adapter.renderIcon('<svg></svg>', '', '')).toBe(
            '<span class="ux-datatables-icon"><svg></svg></span>'
        )
    })

    it('escapes the icon variant and tooltip', () => {
        expect(adapter.renderIcon('<svg></svg>', 'x" onx="y', 'a<b>')).toBe(
            '<span class="ux-datatables-icon text-x&quot; onx=&quot;y" title="a&lt;b&gt;"><svg></svg></span>'
        )
    })

    it('renders a checked switch with Bootstrap classes', () => {
        const html = adapter.renderSwitch({
            checked: true,
            disabled: false,
            ariaLabel: 'ON',
            dataId: '42',
            dataUrl: '/toggle',
            dataField: 'active',
            dataMethod: 'PATCH',
        })

        expect(html).toBe(
            '<div class="form-check form-switch m-0">' +
                '<input class="form-check-input boolean-switch-action" type="checkbox" role="switch"' +
                ' aria-label="ON"' +
                ' data-id="42"' +
                ' data-url="/toggle"' +
                ' data-field="active"' +
                ' data-method="PATCH"' +
                ' checked>' +
                '</div>'
        )
    })

    it('renders a disabled unchecked switch', () => {
        const html = adapter.renderSwitch({
            checked: false,
            disabled: true,
            ariaLabel: 'OFF',
            dataId: '',
            dataUrl: '/toggle',
            dataField: 'active',
            dataMethod: 'PATCH',
        })

        expect(html).toContain(' disabled>')
        expect(html).not.toContain(' checked')
    })
})
