import { describe, expect, it } from 'vitest'
import { unwrapStaleDataTableMarkup } from '../unwrapStaleDataTableMarkup.js'

describe('unwrapStaleDataTableMarkup', () => {
    it('unwraps a table nested in a stale .dt-container', () => {
        const root = document.createElement('div')
        const container = document.createElement('div')
        container.className = 'dt-container'
        const table = document.createElement('table')
        container.appendChild(table)
        root.appendChild(container)

        unwrapStaleDataTableMarkup(table)

        expect(root.contains(container)).toBe(false)
        expect(root.contains(table)).toBe(true)
        expect(table.parentNode).toBe(root)
    })

    it('unwraps a table nested in a stale .dataTables_wrapper', () => {
        const root = document.createElement('div')
        const container = document.createElement('div')
        container.className = 'dataTables_wrapper'
        const table = document.createElement('table')
        container.appendChild(table)
        root.appendChild(container)

        unwrapStaleDataTableMarkup(table)

        expect(root.contains(container)).toBe(false)
        expect(table.parentNode).toBe(root)
    })

    it('does nothing when the table is not wrapped', () => {
        const root = document.createElement('div')
        const table = document.createElement('table')
        root.appendChild(table)

        unwrapStaleDataTableMarkup(table)

        expect(table.parentNode).toBe(root)
    })

    it('does nothing when the wrapper has no parent', () => {
        const container = document.createElement('div')
        container.className = 'dt-container'
        const table = document.createElement('table')
        container.appendChild(table)

        expect(() => unwrapStaleDataTableMarkup(table)).not.toThrow()
        expect(table.parentNode).toBe(container)
    })

    it('does not unwrap a distant ancestor sharing the wrapper class name', () => {
        const appContainer = document.createElement('div')
        appContainer.className = 'dt-container'
        const sibling = document.createElement('span')
        sibling.textContent = 'application content'
        const wrapper = document.createElement('div')
        const table = document.createElement('table')
        wrapper.appendChild(table)
        appContainer.appendChild(sibling)
        appContainer.appendChild(wrapper)

        unwrapStaleDataTableMarkup(table)

        expect(appContainer.contains(sibling)).toBe(true)
        expect(table.parentNode).toBe(wrapper)
    })
})
