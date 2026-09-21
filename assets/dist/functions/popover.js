export function createPopover({ wrapper, panel, toggle }) {
    let documentClickHandler = null;
    let keydownHandler = null;
    const isOpen = () => !panel.hidden;
    const close = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (documentClickHandler) {
            document.removeEventListener('mousedown', documentClickHandler);
            documentClickHandler = null;
        }
        if (keydownHandler) {
            document.removeEventListener('keydown', keydownHandler);
            keydownHandler = null;
        }
    };
    const open = () => {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        documentClickHandler = (event) => {
            if (!wrapper.contains(event.target)) {
                close();
            }
        };
        document.addEventListener('mousedown', documentClickHandler);
        keydownHandler = (event) => {
            if (event.key === 'Escape') {
                close();
                toggle.focus();
            }
        };
        document.addEventListener('keydown', keydownHandler);
    };
    return {
        open,
        close,
        isOpen,
        toggle: () => (isOpen() ? close() : open()),
    };
}
//# sourceMappingURL=popover.js.map