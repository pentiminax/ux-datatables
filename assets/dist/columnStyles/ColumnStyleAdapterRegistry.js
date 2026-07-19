import { BootstrapColumnStyleAdapter } from './BootstrapColumnStyleAdapter.js';
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js';
export class ColumnStyleAdapterRegistry {
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
export const columnStyleAdapters = new ColumnStyleAdapterRegistry()
    .register('bs', () => new BootstrapColumnStyleAdapter())
    .register('bs4', () => new BootstrapColumnStyleAdapter())
    .register('bs5', () => new BootstrapColumnStyleAdapter())
    .register('dt', () => new TailwindColumnStyleAdapter())
    .register('bm', () => new TailwindColumnStyleAdapter())
    .register('zf', () => new TailwindColumnStyleAdapter())
    .register('jqui', () => new TailwindColumnStyleAdapter())
    .register('se', () => new TailwindColumnStyleAdapter());
//# sourceMappingURL=ColumnStyleAdapterRegistry.js.map