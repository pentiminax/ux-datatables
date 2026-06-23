import { BootstrapModalAdapter } from './BootstrapModalAdapter.js';
import { DialogModalAdapter } from './DialogModalAdapter.js';
export class ModalAdapterRegistry {
    constructor() {
        this.factories = new Map();
    }
    register(key, factory) {
        this.factories.set(key, factory);
        return this;
    }
    get(key) {
        return this.factories.get(key) ?? null;
    }
}
export const modalAdapters = new ModalAdapterRegistry()
    .register('dt', () => new DialogModalAdapter())
    .register('bs', () => new BootstrapModalAdapter())
    .register('bs4', () => new BootstrapModalAdapter())
    .register('bs5', () => new BootstrapModalAdapter());
//# sourceMappingURL=ModalAdapterRegistry.js.map