import { afterEach, describe, expect, it, vi } from 'vitest'
import { applyTableLanguage, bindRowFeatures } from '../rowFeatures.js'

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('applyTableLanguage', () => {
    it('applies the empty state on top of the loaded catalog', async () => {
        const payload: Record<string, any> = {
            locale: 'fr',
            emptyState: { heading: 'Rien ici' },
        }

        await applyTableLanguage(payload)

        // Catalog keys survive, which proves the empty state ran after the catalog replaced `language`.
        expect(payload.language.search).toBeTypeOf('string')
        expect(payload.language.emptyTable).toContain('Rien ici')
        expect(payload.language.zeroRecords).toContain('dt-empty-state')
    })

    it('leaves the catalog alone without an empty state', async () => {
        const payload: Record<string, any> = { locale: 'fr' }

        await applyTableLanguage(payload)

        expect(payload.language.emptyTable).not.toContain('dt-empty-state')
    })
})

describe('bindRowFeatures', () => {
    function setup(payload: Record<string, any>, filterBar: { resetFilters: () => void } | null) {
        const assign = vi.fn()
        vi.stubGlobal('location', { assign })
        const element = document.createElement('table')
        element.innerHTML =
            '<tbody><tr data-record-url="/r/1"><td class="cell">x</td></tr>' +
            '<tr><td><button data-dt-empty-reset>r</button></td></tr></tbody>'
        const columnsSearch = vi.fn()
        const table = { search: vi.fn(), columns: () => ({ search: columnsSearch }) }

        bindRowFeatures(payload, element, () => table, filterBar)

        return { assign, element, table, columnsSearch }
    }

    function click(element: Element, selector: string): void {
        element
            .querySelector(selector)
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    }

    it('binds the record click only when recordUrl is exactly true', () => {
        const on = setup({ recordUrl: true }, null)
        click(on.element, '.cell')
        expect(on.assign).toHaveBeenCalledWith('/r/1')

        for (const value of [undefined, false, 'true', 1]) {
            const off = setup({ recordUrl: value }, null)
            click(off.element, '.cell')
            expect(off.assign).not.toHaveBeenCalled()
        }
    })

    it('resets search, column searches and filters from the empty state button', () => {
        const filterBar = { resetFilters: vi.fn() }
        const { element, table, columnsSearch } = setup({ emptyState: {} }, filterBar)

        click(element, '[data-dt-empty-reset]')

        expect(table.search).toHaveBeenCalledWith('')
        expect(columnsSearch).toHaveBeenCalledWith('')
        expect(filterBar.resetFilters).toHaveBeenCalledOnce()
    })

    it('binds no reset without a filter bar or an empty state', () => {
        const filterBar = { resetFilters: vi.fn() }

        click(setup({ emptyState: {} }, null).element, '[data-dt-empty-reset]')
        click(setup({}, filterBar).element, '[data-dt-empty-reset]')

        expect(filterBar.resetFilters).not.toHaveBeenCalled()
    })
})
