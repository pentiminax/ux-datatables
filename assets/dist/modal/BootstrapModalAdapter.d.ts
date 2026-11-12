import type { ModalAdapter, ModalHandlers } from './ModalAdapter.js';
export declare class BootstrapModalAdapter implements ModalAdapter {
    private modalRoot;
    private modalBody;
    private submitButton;
    private modalInstance;
    private handlers;
    private open;
    private notifyCancelOnHide;
    private hideResolver;
    private readonly hiddenListener;
    private readonly submitListener;
    show(html: string, handlers: ModalHandlers): Promise<void>;
    replaceBody(html: string): void;
    hide(): Promise<void>;
    isOpen(): boolean;
    private cleanup;
}
