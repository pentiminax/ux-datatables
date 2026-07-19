import { describe, expect, it, vi } from 'vitest'
import { FilterBar, type FilterDefinition, hasFilters } from '../filters.js'

function makeBar(filters: FilterDefinition[]) {
    const payload: Record<string, any> = {
        filters,
        ajax: { url: '/data', data: { extra: 'kept' } },
    }
    const bar = new FilterBar(payload, 'dt')
    bar.attachToPayload(payload)
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

    it('merges applied filter values into the ajax data, preserving static data', () => {
        const { bar, payload } = makeBar([{ name: 'name', type: 'text' }])
        const wrapper = bar.render(vi.fn())
        ;(wrapper.querySelector('input[type="search"]') as HTMLInputElement).value = 'john'
        clickApply(wrapper)

        const data: Record<string, any> = { draw: 1 }
        const result = payload.ajax.data(data)

        expect(result).toMatchObject({
            draw: 1,
            extra: 'kept',
            filters: { name: 'john' },
        })
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
})
