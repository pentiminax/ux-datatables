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

    it('renders a number range as two number inputs with translated placeholders', () => {
        const { bar } = makeBar([
            {
                name: 'price',
                type: 'numberRange',
                label: 'Price',
                minPlaceholder: 'Au moins',
                maxPlaceholder: 'Au plus',
            },
        ])
        const wrapper = bar.render(vi.fn())
        const inputs = wrapper.querySelectorAll<HTMLInputElement>(
            '.dt-filter-range input[type="number"]'
        )

        expect(inputs).toHaveLength(2)
        expect(inputs[0].name).toBe('filters[price][min]')
        expect(inputs[1].name).toBe('filters[price][max]')
        expect(inputs[0].placeholder).toBe('Au moins')
        expect(inputs[1].placeholder).toBe('Au plus')
        expect(inputs[0].getAttribute('aria-label')).toBe('Price (Au moins)')
    })

    it('falls back to English Min and Max placeholders', () => {
        const { bar } = makeBar([{ name: 'price', type: 'numberRange' }])
        const inputs = bar
            .render(vi.fn())
            .querySelectorAll<HTMLInputElement>('.dt-filter-range input')

        expect([inputs[0].placeholder, inputs[1].placeholder]).toEqual(['Min', 'Max'])
    })

    it('applies a number range with only the provided bounds, keeping zero', () => {
        const { bar } = makeBar([{ name: 'price', type: 'numberRange' }])
        const wrapper = bar.render(vi.fn())
        const inputs = wrapper.querySelectorAll<HTMLInputElement>('input[type="number"]')

        inputs[0].value = '0'
        clickApply(wrapper)
        expect(bar.collectValues()).toEqual({ price: { min: '0' } })

        inputs[0].value = ''
        inputs[1].value = '12.5'
        clickApply(wrapper)
        expect(bar.collectValues()).toEqual({ price: { max: '12.5' } })

        inputs[1].value = ''
        clickApply(wrapper)
        expect(bar.collectValues()).toEqual({})
    })

    it('does not send exponent notation the server would ignore', () => {
        const { bar } = makeBar([{ name: 'price', type: 'numberRange' }])
        const wrapper = bar.render(vi.fn())
        const inputs = wrapper.querySelectorAll<HTMLInputElement>('input[type="number"]')

        inputs[0].value = '1e3'
        inputs[1].value = '7'
        clickApply(wrapper)

        expect(bar.collectValues()).toEqual({ price: { max: '7' } })
    })

    it('restores a saved number range and drops non-numeric bounds', () => {
        const { bar } = makeBar([{ name: 'price', type: 'numberRange' }])
        const wrapper = bar.render(vi.fn())

        bar.restoreValues({ price: { min: '5', max: '1e9; DROP' } })

        expect(bar.collectValues()).toEqual({ price: { min: '5' } })
        const inputs = wrapper.querySelectorAll<HTMLInputElement>('input[type="number"]')
        expect([inputs[0].value, inputs[1].value]).toEqual(['5', ''])

        bar.restoreValues({ price: { min: 'abc', from: '2024-01-01' } })
        expect(bar.collectValues()).toEqual({})
        expect(inputs[0].value).toBe('')
    })

    it('resets a number range', () => {
        const { bar } = makeBar([{ name: 'price', type: 'numberRange' }])
        const wrapper = bar.render(vi.fn())
        const inputs = wrapper.querySelectorAll<HTMLInputElement>('input[type="number"]')
        inputs[0].value = '3'
        clickApply(wrapper)

        clickReset(wrapper)

        expect(inputs[0].value).toBe('')
        expect(bar.collectValues()).toEqual({})
    })

    it('keeps the server order of options sent as value and label pairs', () => {
        const { bar } = makeBar([
            {
                name: 'category',
                type: 'select',
                options: [
                    ['10', 'Alpha'],
                    ['2', 'Beta'],
                ],
            },
        ])
        const wrapper = bar.render(vi.fn())
        const values = [...wrapper.querySelectorAll<HTMLOptionElement>('select > option')].map(
            (o) => o.value
        )

        expect(values).toEqual(['', '10', '2'])

        bar.restoreValues({ category: '2' })
        expect(bar.collectValues()).toEqual({ category: '2' })
        bar.restoreValues({ category: '3' })
        expect(bar.collectValues()).toEqual({})
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
