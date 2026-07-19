import { BootstrapColumnStyleAdapter } from './BootstrapColumnStyleAdapter.js'
import type { ColumnStyleAdapterFactory } from './ColumnStyleAdapter.js'
import { TailwindColumnStyleAdapter } from './TailwindColumnStyleAdapter.js'

export class ColumnStyleAdapterRegistry {
    private readonly factories = new Map<string, ColumnStyleAdapterFactory>()

    register(key: string, factory: ColumnStyleAdapterFactory): this {
        this.factories.set(key, factory)

        return this
    }

    get(key: string): ColumnStyleAdapterFactory | null {
        return this.factories.get(key) ?? null
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
    .register('se', () => new TailwindColumnStyleAdapter())
