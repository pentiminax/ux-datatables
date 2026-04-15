import { BootstrapModalAdapter } from './BootstrapModalAdapter.js'
import type { ModalAdapterFactory } from './ModalAdapter.js'

export class ModalAdapterRegistry {
    private readonly factories = new Map<string, ModalAdapterFactory>()

    register(key: string, factory: ModalAdapterFactory): this {
        this.factories.set(key, factory)

        return this
    }

    get(key: string): ModalAdapterFactory | null {
        return this.factories.get(key) ?? null
    }
}

export const modalAdapters = new ModalAdapterRegistry()
    .register('bs', () => new BootstrapModalAdapter())
    .register('bs4', () => new BootstrapModalAdapter())
    .register('bs5', () => new BootstrapModalAdapter())
