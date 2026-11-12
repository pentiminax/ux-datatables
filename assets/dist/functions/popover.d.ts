export interface PopoverOptions {
    wrapper: HTMLElement;
    panel: HTMLElement;
    toggle: HTMLElement;
}
export interface Popover {
    open: () => void;
    close: () => void;
    toggle: () => void;
    isOpen: () => boolean;
}
export declare function createPopover({ wrapper, panel, toggle }: PopoverOptions): Popover;
