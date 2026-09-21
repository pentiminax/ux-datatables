export interface PopoverOptions {
    wrapper: HTMLElement
    panel: HTMLElement
    toggle: HTMLElement
}

export interface Popover {
    open: () => void
    close: () => void
    toggle: () => void
    isOpen: () => boolean
}

/**
 * Show and hide a panel anchored to a toggle button.
 *
 * Closes on a click outside the wrapper and on Escape, and keeps `aria-expanded` in step.
 */
export function createPopover({ wrapper, panel, toggle }: PopoverOptions): Popover {
    let documentClickHandler: ((event: MouseEvent) => void) | null = null
    let keydownHandler: ((event: KeyboardEvent) => void) | null = null

    const isOpen = (): boolean => !panel.hidden

    const close = (): void => {
        panel.hidden = true
        toggle.setAttribute('aria-expanded', 'false')

        if (documentClickHandler) {
            document.removeEventListener('mousedown', documentClickHandler)
            documentClickHandler = null
        }

        if (keydownHandler) {
            document.removeEventListener('keydown', keydownHandler)
            keydownHandler = null
        }
    }

    const open = (): void => {
        panel.hidden = false
        toggle.setAttribute('aria-expanded', 'true')

        documentClickHandler = (event: MouseEvent) => {
            if (!wrapper.contains(event.target as Node)) {
                close()
            }
        }
        document.addEventListener('mousedown', documentClickHandler)

        keydownHandler = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close()
                toggle.focus()
            }
        }
        document.addEventListener('keydown', keydownHandler)
    }

    return {
        open,
        close,
        isOpen,
        toggle: () => (isOpen() ? close() : open()),
    }
}
