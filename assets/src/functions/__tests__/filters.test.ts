import { describe, expect, it, vi } from 'vitest'
import { FilterBar, type FilterDefinition, hasFilters } from '../filters.js'

function makeBar(filters: FilterDefinition[]) {
    const payload: Record<string, any> = { filters }
    const bar = new FilterBar(payload, 'dt')
    return { bar, payload }
}

function clickApply(wrapper: HTMLElement) {
    ;(wrapper.querySelector('.dt-filters-apply') as HTMLButtonElement).click()
}

function clickReset(wrapper: HTMLElement) {
    ;(wrapper.querySelector('.dt-filters-reset') as HTMLButtonElement).click()
}

describe('hasFilters', () => {
    it('returns true only when filters are present', () => {
        expect(hasFilters({ filters: [{ name: 'a', type: 'text' }] })).toBe(true)
        expect(hasFilters({ filters: [] })).toBe(false)
        expect(hasFilters({})).toBe(false)
    })
})

describe('FilterBar', () => {
    it('renders a toggle, a badge and one control per definition', () => {
        const { bar } = makeBar([
            { name: 'name', type: 'text' },
            { name: 'status', type: 'select', options: { draft: 'Draft' } },
            { name: 'active', type: 'ternary' },
            { name: 'createdAt', type: 'dateRange' },
            { name: 'vip', type: 'checkbox' },
        ])

        const wrapper = bar.render(vi.fn())

        expect(wrapper.querySelector('.dt-filters-toggle')).not.toBeNull()
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('0')
        expect(wrapper.querySelectorAll('.dt-filters-popover__body .dt-filter')).toHaveLength(5)
    })

    it('uses built-in English defaults for the chrome strings', () => {
        const { bar } = makeBar([{ name: 'status', type: 'select', options: { draft: 'Draft' } }])
        const wrapper = bar.render(vi.fn())

        expect(wrapper.querySelector('.dt-filters-popover__title')?.textContent).toBe('Filters')
        expect(wrapper.querySelector('.dt-filters-reset')?.textContent).toBe('Reset')
        expect(wrapper.querySelector('.dt-filters-apply')?.textContent).toBe('Apply filters')
        expect(wrapper.querySelector('.dt-filters-toggle')?.getAttribute('aria-label')).toBe(
            'Filters'
        )
        expect((wrapper.querySelector('select > option') as HTMLOptionElement).textContent).toBe(
            'All'
        )
    })

    it('applies filterLabels overrides to the chrome strings', () => {
        const payload: Record<string, any> = {
            filters: [{ name: 'status', type: 'select', options: { draft: 'Draft' } }],
            filterLabels: {
                title: 'Filtres',
                reset: 'Réinitialiser',
                apply: 'Appliquer',
                all: 'Tous',
            },
            ajax: { url: '/data' },
        }
        const bar = new FilterBar(payload, 'dt')
        const wrapper = bar.render(vi.fn())

        expect(wrapper.querySelector('.dt-filters-popover__title')?.textContent).toBe('Filtres')
        expect(wrapper.querySelector('.dt-filters-reset')?.textContent).toBe('Réinitialiser')
        expect(wrapper.querySelector('.dt-filters-apply')?.textContent).toBe('Appliquer')
        expect(wrapper.querySelector('.dt-filters-toggle')?.getAttribute('aria-label')).toBe(
            'Filtres'
        )
        expect((wrapper.querySelector('select > option') as HTMLOptionElement).textContent).toBe(
            'Tous'
        )
    })

    it('does not apply values until "Apply filters" is clicked', () => {
        const reload = vi.fn()
        const { bar } = makeBar([{ name: 'name', type: 'text' }])
        const wrapper = bar.render(reload)
        ;(wrapper.querySelector('input[type="search"]') as HTMLInputElement).value = 'john'

        expect(bar.collectValues()).toEqual({})
        expect(reload).not.toHaveBeenCalled()

        clickApply(wrapper)

        expect(bar.collectValues()).toEqual({ name: 'john' })
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('applies only non-empty values and updates the badge count', () => {
        const { bar } = makeBar([
            { name: 'name', type: 'text' },
            { name: 'status', type: 'select', options: { draft: 'Draft', done: 'Done' } },
        ])

        const wrapper = bar.render(vi.fn())
        ;(wrapper.querySelector('input[type="search"]') as HTMLInputElement).value = '  '
        ;(wrapper.querySelector('select') as HTMLSelectElement).value = 'done'
        clickApply(wrapper)

        expect(bar.collectValues()).toEqual({ status: 'done' })
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('1')
    })

    it('wraps date range bounds in dt-filter-range', () => {
        const { bar } = makeBar([{ name: 'lastLoginAt', type: 'dateRange' }])
        const wrapper = bar.render(vi.fn())
        const range = wrapper.querySelector('.dt-filter-range')

        expect(range).toBeInstanceOf(HTMLElement)
        const rangeEl = range as HTMLElement
        expect(rangeEl.querySelectorAll('input[type="date"]')).toHaveLength(2)
        expect((rangeEl.children[0] as HTMLInputElement).name).toBe('filters[lastLoginAt][from]')
        expect((rangeEl.children[1] as HTMLInputElement).name).toBe('filters[lastLoginAt][to]')
    })

    it('applies a date range as an object with only provided bounds', () => {
        const { bar } = makeBar([{ name: 'createdAt', type: 'dateRange' }])
        const wrapper = bar.render(vi.fn())
        const inputs = wrapper.querySelectorAll('input[type="date"]')
        ;(inputs[0] as HTMLInputElement).value = '2024-01-01'
        clickApply(wrapper)

        expect(bar.collectValues()).toEqual({ createdAt: { from: '2024-01-01' } })
    })

    it('clears controls and applied values on reset', () => {
        const reload = vi.fn()
        const { bar } = makeBar([{ name: 'status', type: 'select', options: { a: 'A' } }])
        const wrapper = bar.render(reload)

        const select = wrapper.querySelector('select') as HTMLSelectElement
        select.value = 'a'
        clickApply(wrapper)
        expect(bar.collectValues()).toEqual({ status: 'a' })

        clickReset(wrapper)

        expect(select.value).toBe('')
        expect(bar.collectValues()).toEqual({})
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('0')
        expect(reload).toHaveBeenCalledTimes(2)
    })

    it('toggles the popover open and closed', () => {
        const { bar } = makeBar([{ name: 'name', type: 'text' }])
        const wrapper = bar.render(vi.fn())
        const toggle = wrapper.querySelector('.dt-filters-toggle') as HTMLButtonElement
        const popover = wrapper.querySelector('.dt-filters-popover') as HTMLDivElement

        expect(popover.hidden).toBe(true)
        toggle.click()
        expect(popover.hidden).toBe(false)
        expect(toggle.getAttribute('aria-expanded')).toBe('true')
        toggle.click()
        expect(popover.hidden).toBe(true)
    })

    it('restores previously saved filter values via restoreValues', () => {
        const { bar } = makeBar([
            { name: 'name', type: 'text' },
            { name: 'status', type: 'select', options: { active: 'Active' } },
            { name: 'createdAt', type: 'dateRange' },
            { name: 'vip', type: 'checkbox' },
        ])
        const wrapper = bar.render(vi.fn())

        bar.restoreValues({
            name: 'alice',
            status: 'active',
            createdAt: { from: '2026-01-01', to: '2026-01-31' },
            vip: '1',
        })

        expect(bar.collectValues()).toEqual({
            name: 'alice',
            status: 'active',
            createdAt: { from: '2026-01-01', to: '2026-01-31' },
            vip: '1',
        })
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('4')
        expect(
            (wrapper.querySelector('input[name="filters[name]"]') as HTMLInputElement).value
        ).toBe('alice')
        expect(
            (wrapper.querySelector('select[name="filters[status]"]') as HTMLSelectElement).value
        ).toBe('active')
        expect(
            (wrapper.querySelector('input[name="filters[createdAt][from]"]') as HTMLInputElement)
                .value
        ).toBe('2026-01-01')
        expect(
            (wrapper.querySelector('input[name="filters[vip]"]') as HTMLInputElement).checked
        ).toBe(true)
    })

    it('shows the header reset button only while filters are applied', () => {
        const reload = vi.fn()
        const payload: Record<string, any> = {
            filters: [{ name: 'name', type: 'text' }],
            showHeaderResetButton: true,
            ajax: { url: '/data' },
        }
        const bar = new FilterBar(payload, 'dt')
        const wrapper = bar.render(reload)

        const headerReset = wrapper.querySelector('.dt-filters-header-reset') as HTMLButtonElement
        expect(headerReset).not.toBeNull()
        expect(headerReset.hidden).toBe(true)

        ;(wrapper.querySelector('input[type="search"]') as HTMLInputElement).value = 'john'
        clickApply(wrapper)

        expect(headerReset.hidden).toBe(false)

        headerReset.click()

        expect(headerReset.hidden).toBe(true)
        expect(bar.collectValues()).toEqual({})
        expect((wrapper.querySelector('input[type="search"]') as HTMLInputElement).value).toBe('')
        expect(reload).toHaveBeenCalledTimes(2)
    })

    it('safely renders header reset label containing markup as text', () => {
        const payload: Record<string, any> = {
            filters: [{ name: 'name', type: 'text' }],
            filterLabels: { reset: '<img src=x onerror=alert(1)>' },
            showHeaderResetButton: true,
            ajax: { url: '/data' },
        }
        const bar = new FilterBar(payload, 'dt')
        const wrapper = bar.render(vi.fn())

        const headerReset = wrapper.querySelector('.dt-filters-header-reset') as HTMLButtonElement
        expect(headerReset.querySelector('img')).toBeNull()
        expect(headerReset.querySelector('span')?.textContent).toBe('<img src=x onerror=alert(1)>')
    })

    it('ignores unsupported filters or invalid select options during restoreValues', () => {
        const { bar } = makeBar([
            { name: 'status', type: 'select', options: { active: 'Active', pending: 'Pending' } },
            {
                name: 'role',
                type: 'select',
                multiple: true,
                options: { admin: 'Admin', user: 'User' },
            },
            { name: 'active', type: 'ternary' },
        ])
        const wrapper = bar.render(vi.fn())

        bar.restoreValues({
            status: 'removed_option', // invalid option
            role: ['admin', 'obsolete_role'], // partial invalid
            active: 'invalid_boolean', // invalid ternary
            unknownFilter: 'some_value', // deleted filter
        })

        expect(bar.collectValues()).toEqual({
            role: ['admin'],
        })
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('1')
        expect(
            (wrapper.querySelector('select[name="filters[status]"]') as HTMLSelectElement).value
        ).toBe('')
    })

    it('drops malformed persisted values instead of throwing during restoreValues', () => {
        const { bar } = makeBar([
            { name: 'name', type: 'text' },
            { name: 'createdAt', type: 'dateRange' },
            { name: 'status', type: 'select', options: { active: 'Active' } },
        ])
        const wrapper = bar.render(vi.fn())

        expect(() =>
            bar.restoreValues({
                name: 42,
                createdAt: { from: 5, to: null },
                status: { from: 'active' },
            })
        ).not.toThrow()
        expect(() => bar.restoreValues('not-an-object')).not.toThrow()

        expect(bar.collectValues()).toEqual({})
        expect(wrapper.querySelector('.dt-filters-badge')?.textContent).toBe('0')
    })

    it('restores numeric multi-select values as selected string options', () => {
        const { bar } = makeBar([
            {
                name: 'role',
                type: 'select',
                multiple: true,
                options: { '1': 'Admin', '2': 'User' },
            },
        ])
        const wrapper = bar.render(vi.fn())

        bar.restoreValues({ role: [1, 'unknown'] })

        const select = wrapper.querySelector('select[name="filters[role]"]') as HTMLSelectElement
        expect(bar.collectValues()).toEqual({ role: ['1'] })
        expect([...select.selectedOptions].map((option) => option.value)).toEqual(['1'])
    })
})
