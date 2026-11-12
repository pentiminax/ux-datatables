import type { ColumnStyleAdapterFactory } from './ColumnStyleAdapter.js';
export declare class ColumnStyleAdapterRegistry {
    private readonly factories;
    register(key: string, factory: ColumnStyleAdapterFactory): this;
    get(key: string): ColumnStyleAdapterFactory | null;
}
export declare const columnStyleAdapters: ColumnStyleAdapterRegistry;
