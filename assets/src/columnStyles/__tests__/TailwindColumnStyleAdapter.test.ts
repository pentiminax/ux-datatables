import { describe, expect, it } from 'vitest'
import { TailwindColumnStyleAdapter } from '../TailwindColumnStyleAdapter.js'

describe('TailwindColumnStyleAdapter', () => {
    const adapter = new TailwindColumnStyleAdapter()

    it.each([
        ['success', 'bg-green-100 text-green-800'],
        ['warning', 'bg-yellow-100 text-yellow-800'],
        ['danger', 'bg-red-100 text-red-800'],
        ['info', 'bg-sky-100 text-sky-800'],
        ['primary', 'bg-blue-100 text-blue-800'],
        ['secondary', 'bg-gray-100 text-gray-800'],
        ['light', 'bg-gray-50 text-gray-700'],
        ['dark', 'bg-gray-800 text-gray-100'],
    ] as const)('renders a %s badge with Tailwind utilities', (variant, colorClasses) => {
        expect(adapter.renderBadge('Label', variant)).toBe(
            `<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ${colorClasses}">Label</span>`
        )
    })

    it('falls back to secondary for unknown variants', () => {
        expect(adapter.renderBadge('Label', 'unknown')).toBe(
            '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-800">Label</span>'
        )
    })

    it('escapes badge label', () => {
        expect(adapter.renderBadge('<b>X</b>', 'success')).toBe(
            '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800">&lt;b&gt;X&lt;/b&gt;</span>'
        )
    })

    it('renders an icon with the mapped color class and tooltip', () => {
        expect(adapter.renderIcon('<svg></svg>', 'success', 'Active')).toBe(
            '<span class="inline-flex text-green-600" title="Active"><svg></svg></span>'
        )
    })

    it('falls back to secondary for unknown icon variants', () => {
        expect(adapter.renderIcon('<svg></svg>', 'unknown', '')).toBe(
            '<span class="inline-flex text-gray-600"><svg></svg></span>'
        )
    })

    it('escapes the icon tooltip', () => {
        expect(adapter.renderIcon('<svg></svg>', 'danger', 'a<b>')).toBe(
            '<span class="inline-flex text-red-600" title="a&lt;b&gt;"><svg></svg></span>'
        )
    })

    it('renders a checked switch with Tailwind utilities and behavior class', () => {
        const html = adapter.renderSwitch({
            checked: true,
            disabled: false,
            ariaLabel: 'ON',
            dataId: '42',
            dataUrl: '/toggle',
            dataField: 'active',
            dataMethod: 'PATCH',
        })

        expect(html).toContain('peer sr-only boolean-switch-action')
        expect(html).toContain('peer-checked:bg-blue-600')
        expect(html).toContain(' checked>')
        expect(html).toContain('data-id="42"')
        expect(html).toContain('data-url="/toggle"')
        expect(html).toContain('data-field="active"')
        expect(html).toContain('data-method="PATCH"')
        expect(html).not.toContain('form-check')
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
