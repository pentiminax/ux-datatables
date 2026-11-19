import type { StyleFramework } from '../types/styleFramework.js'

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
] as const

export type ExtensionName = (typeof EXTENSION_NAMES)[number]

const PACKAGE_NAMES = [
    'dataTables',
    'buttons',
    'jszip',
    'pdfmake',
    'bootstrap',
    'lucide',
    ...EXTENSION_NAMES,
] as const

export type PackageName = (typeof PACKAGE_NAMES)[number]

/** Imports a package (JS and CSS) and resolves with its module namespace. */
export type PackageLoader = () => Promise<unknown>

/**
 * Maps the optional packages the controller needs to loaders the application registers, so its
 * bundler or import map only ships what the application actually registered.
 */
export class PackageLoaderRegistry {
    private readonly loaders = new Map<string, PackageLoader>()

    /** A loader registered for a framework wins over the one registered for the name alone. */
    register(name: PackageName, loader: PackageLoader, framework?: StyleFramework): this {
        if (!(PACKAGE_NAMES as readonly string[]).includes(name)) {
            throw new Error(
                `Cannot register an unknown package "${name}". Valid names: ${PACKAGE_NAMES.join(', ')}.`
            )
        }

        this.loaders.set(this.key(name, framework), loader)

        return this
    }

    isRegistered(name: PackageName): boolean {
        return this.resolve(name) !== undefined
    }

    /** The frameworks a package has a framework-scoped loader for. */
    frameworksOf(name: PackageName): StyleFramework[] {
        return [...this.loaders.keys()]
            .filter((key) => key.startsWith(`${name}:`))
            .map((key) => key.slice(name.length + 1) as StyleFramework)
    }

    has(name: PackageName, framework?: StyleFramework): boolean {
        return this.resolve(name, framework) !== undefined
    }

    load(name: PackageName, framework?: StyleFramework): Promise<unknown> {
        const loader = this.resolve(name, framework)

        if (!loader) {
            const others = this.frameworksOf(name)
            const hint =
                framework !== undefined && others.length > 0
                    ? ` It is registered only for the ${others.join(', ')} style, not for "${framework}": register it for "${framework}" with packageLoaders.register('${name}', loader, '${framework}').`
                    : ` Install it and register it with packageLoaders.register('${name}', loader).`

            return Promise.reject(new Error(`The "${name}" package is not registered.${hint}`))
        }

        return loader()
    }

    private resolve(name: PackageName, framework?: StyleFramework): PackageLoader | undefined {
        const loader = this.loaders.get(this.key(name, framework)) ?? this.loaders.get(name)

        if (loader || framework !== undefined) {
            return loader
        }

        const [first] = this.frameworksOf(name)

        return first === undefined ? undefined : this.loaders.get(this.key(name, first))
    }

    private key(name: PackageName, framework?: StyleFramework): string {
        return framework === undefined ? name : `${name}:${framework}`
    }
}

export const packageLoaders = new PackageLoaderRegistry()

export function defaultExport<T>(module: unknown): T {
    return ((module as { default?: T }).default ?? module) as T
}
