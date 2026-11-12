import type { StyleFramework } from '../types/styleFramework.js';
export declare const EXTENSION_NAMES: readonly ['colReorder', 'columnControl', 'fixedColumns', 'fixedHeader', 'keyTable', 'responsive', 'rowGroup', 'scroller', 'searchBuilder', 'select'];
export type ExtensionName = (typeof EXTENSION_NAMES)[number];
declare const PACKAGE_NAMES: readonly ["dataTables", "buttons", "jszip", "pdfmake", "bootstrap", "lucide", "colReorder", "columnControl", "fixedColumns", "fixedHeader", "keyTable", "responsive", "rowGroup", "scroller", "searchBuilder", "select"];
export type PackageName = (typeof PACKAGE_NAMES)[number];
export type PackageLoader = () => Promise<unknown>;
type FallbackResolver = (name: PackageName, framework: StyleFramework | undefined) => PackageLoader | undefined;
export declare class PackageLoaderRegistry {
    private readonly loaders;
    private fallback;
    register(name: PackageName, loader: PackageLoader): this;
    has(name: PackageName, framework?: StyleFramework): boolean;
    load(name: PackageName, framework?: StyleFramework): Promise<unknown>;
    setFallback(fallback: FallbackResolver | null): void;
    private resolve;
}
export declare const packageLoaders: PackageLoaderRegistry;
export declare function defaultExport<T>(module: unknown): T;
export {};
