// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { applyThemeSearchPlaceholder } from '../themeSearchField.js'

function container(labelText: string, placeholder = ''): HTMLElement {
    const root = document.createElement('div')
    root.innerHTML = `<div class="dt-search"><label for="s">${labelText}</label><input id="s" type="search" placeholder="${placeholder}"></div>`

    return root
}

describe('applyThemeSearchPlaceholder', () => {
    it('moves the label text into the placeholder', () => {
        const root = container('Rechercher :')

        applyThemeSearchPlaceholder(root)

        expect(root.querySelector('input')?.placeholder).toBe('Rechercher…')
    })

    it('strips the trailing colon whatever spacing the catalog uses', () => {
        const root = container('Search:')

        applyThemeSearchPlaceholder(root)

        expect(root.querySelector('input')?.placeholder).toBe('Search…')
    })

    it('keeps the label in the DOM so the input keeps its accessible name', () => {
        const root = container('Search:')

        applyThemeSearchPlaceholder(root)

        const label = root.querySelector('label')
        expect(label).not.toBeNull()
        expect(label?.classList.contains('dt-search-label-hidden')).toBe(true)
    })

    it('leaves a placeholder the application already set', () => {
        const root = container('Search:', 'Find an invoice')

        applyThemeSearchPlaceholder(root)

        expect(root.querySelector('input')?.placeholder).toBe('Find an invoice')
    })

    it('leaves an empty label alone rather than writing a bare ellipsis', () => {
        const root = container('')

        applyThemeSearchPlaceholder(root)

        expect(root.querySelector('input')?.placeholder).toBe('')
    })

    it('ignores a search cell DataTables has not rendered an input into', () => {
        const root = document.createElement('div')
        root.innerHTML = '<div class="dt-search"><label>Search:</label></div>'

        expect(() => applyThemeSearchPlaceholder(root)).not.toThrow()
    })
})
