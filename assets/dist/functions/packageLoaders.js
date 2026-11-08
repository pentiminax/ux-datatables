export const EXTENSION_NAMES = [
    'colReorder',
    'columnControl',
    'fixedColumns',
    'fixedHeader',
    'keyTable',
    'responsive',
    'rowGroup',
    'scroller',
    'searchBuilder',
    'select',
];
export class PackageLoaderRegistry {
    constructor() {
        this.loaders = new Map();
        this.fallback = null;
    }
    register(name, loader) {
        this.loaders.set(name, loader);
        return this;
    }
    has(name, framework) {
        return this.resolve(name, framework) !== undefined;
    }
    load(name, framework) {
        const loader = this.resolve(name, framework);
        if (!loader) {
            return Promise.reject(new Error(`The "${name}" package is not registered. Install it and register it with packageLoaders.register('${name}', loader).`));
        }
        return loader();
    }
    setFallback(fallback) {
        this.fallback = fallback;
    }
    resolve(name, framework) {
        return this.loaders.get(name) ?? this.fallback?.(name, framework);
    }
}
export const packageLoaders = new PackageLoaderRegistry();
export function defaultExport(module) {
    return (module.default ?? module);
}
//# sourceMappingURL=packageLoaders.js.map