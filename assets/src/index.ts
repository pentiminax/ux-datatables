export { default } from './controller.js'
export { default as DataTableController } from './controller.js'
export { BootstrapModalAdapter } from './modal/BootstrapModalAdapter.js'
export { DialogModalAdapter } from './modal/DialogModalAdapter.js'
export { modalAdapters, ModalAdapterRegistry } from './modal/ModalAdapterRegistry.js'
export type {
    ModalAdapter,
    ModalAdapterConstructor,
    ModalAdapterFactory,
    ModalHandlers,
} from './modal/ModalAdapter.js'
