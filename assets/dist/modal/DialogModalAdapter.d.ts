import type { ModalAdapter, ModalHandlers } from './ModalAdapter.js';
export declare class DialogModalAdapter implements ModalAdapter {
    private dialog;
    private modalBody;
    private submitButton;
    private handlers;
    private notifyCancelOnClose;
    private hideResolver;
    private readonly cancelButtons;
    private readonly closeListener;
    private readonly submitListener;
    private readonly cancelListener;
    show(html: string, handlers: ModalHandlers): Promise<void>;
    replaceBody(html: string): void;
    hide(): Promise<void>;
    isOpen(): boolean;
    private cleanup;
}
