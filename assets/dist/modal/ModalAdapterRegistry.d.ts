import type { ModalAdapterFactory } from './ModalAdapter.js';
export declare class ModalAdapterRegistry {
    private readonly factories;
    register(key: string, factory: ModalAdapterFactory): this;
    get(key: string): ModalAdapterFactory | null;
}
export declare const modalAdapters: ModalAdapterRegistry;
