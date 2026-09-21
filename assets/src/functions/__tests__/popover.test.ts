import { beforeEach, describe, expect, it } from 'vitest'
import { createPopover } from '../popover.js'

describe('createPopover', () => {
    let wrapper: HTMLDivElement
    let panel: HTMLDivElement
    let toggle: HTMLButtonElement

    beforeEach(() => {
        document.body.innerHTML = ''
        wrapper = document.createElement('div')
        panel = document.createElement('div')
        panel.hidden = true
        toggle = document.createElement('button')
        toggle.setAttribute('aria-expanded', 'false')
        wrapper.append(toggle, panel)
        document.body.appendChild(wrapper)
    })

    const dispatchMouseDown = (target: Node): void => {
        target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    }

    it('shows the panel and reports the state through aria-expanded', () => {
        const popover = createPopover({ wrapper, panel, toggle })

        popover.toggle()

        expect(panel.hidden).toBe(false)
        expect(toggle.getAttribute('aria-expanded')).toBe('true')
        expect(popover.isOpen()).toBe(true)

        popover.toggle()

        expect(panel.hidden).toBe(true)
        expect(toggle.getAttribute('aria-expanded')).toBe('false')
    })

    it('closes on a click outside the wrapper, not inside it', () => {
        const popover = createPopover({ wrapper, panel, toggle })
        popover.open()

        dispatchMouseDown(panel)
        expect(popover.isOpen()).toBe(true)

        dispatchMouseDown(document.body)
        expect(popover.isOpen()).toBe(false)
    })

    it('closes on Escape and gives the focus back to the toggle', () => {
        const popover = createPopover({ wrapper, panel, toggle })
        popover.open()

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

        expect(popover.isOpen()).toBe(false)
        expect(document.activeElement).toBe(toggle)
    })

    it('stops listening once closed', () => {
        const popover = createPopover({ wrapper, panel, toggle })
        popover.open()
        popover.close()

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
        dispatchMouseDown(document.body)

        expect(popover.isOpen()).toBe(false)
    })
})
