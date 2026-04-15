export interface ModalHandlers {
    onSubmit: (formData: Record<string, unknown>) => Promise<void>
    onCancel?: () => void
}

export interface ModalAdapter {
    show(html: string, handlers: ModalHandlers): Promise<void>
    replaceBody(html: string): void
    hide(): Promise<void>
    isOpen(): boolean
}

export type ModalAdapterConstructor = new () => ModalAdapter

export type ModalAdapterFactory = () =>
    | ModalAdapter
    | ModalAdapterConstructor
    | Promise<ModalAdapter | ModalAdapterConstructor>
