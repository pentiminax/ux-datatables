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
        return this.resolve(name) !== undefined;
    }
    frameworksOf(name) {
        return [...this.loaders.keys()]
            .filter((key) => key.startsWith(`${name}:`))
            .map((key) => key.slice(name.length + 1));
    }
    has(name, framework) {
        return this.resolve(name, framework) !== undefined;
    }
    load(name, framework) {
        const loader = this.resolve(name, framework);
        if (!loader) {
            const others = this.frameworksOf(name);
            const hint = framework !== undefined && others.length > 0
                ? ` It is registered only for the ${others.join(', ')} style, not for "${framework}": register it for "${framework}" with packageLoaders.register('${name}', loader, '${framework}').`
                : ` Install it and register it with packageLoaders.register('${name}', loader).`;
            return Promise.reject(new Error(`The "${name}" package is not registered.${hint}`));
        }
        return loader();
    }
    resolve(name, framework) {
        const loader = this.loaders.get(this.key(name, framework)) ?? this.loaders.get(name);
        if (loader || framework !== undefined) {
            return loader;
        }
        const [first] = this.frameworksOf(name);
        return first === undefined ? undefined : this.loaders.get(this.key(name, first));
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