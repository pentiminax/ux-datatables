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
const PACKAGE_NAMES = [
    'dataTables',
    'buttons',
    'jszip',
    'pdfmake',
    'bootstrap',
    'lucide',
    ...EXTENSION_NAMES,
];
export class PackageLoaderRegistry {
    constructor() {
        this.loaders = new Map();
    }
    register(name, loader, framework) {
        if (!PACKAGE_NAMES.includes(name)) {
            throw new Error(`Cannot register an unknown package "${name}". Valid names: ${PACKAGE_NAMES.join(', ')}.`);
        }
        this.loaders.set(this.key(name, framework), loader);
        return this;
    }
    isRegistered(name) {
        return this.loaders.has(name);
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
    resolve(name, framework) {
        return this.loaders.get(this.key(name, framework)) ?? this.loaders.get(name);
    }
    key(name, framework) {
        return framework === undefined ? name : `${name}:${framework}`;
    }
}
export const packageLoaders = new PackageLoaderRegistry();
export function defaultExport(module) {
    return (module.default ?? module);
}
//# sourceMappingURL=packageLoaders.js.map