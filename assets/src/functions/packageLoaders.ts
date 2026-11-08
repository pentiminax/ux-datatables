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

export type PackageName =
    | 'dataTables'
    | 'buttons'
    | 'jszip'
    | 'pdfmake'
    | 'bootstrap'
    | 'lucide'
    | ExtensionName

/** Imports a package (JS and CSS) and resolves with its module namespace. */
export type PackageLoader = () => Promise<unknown>

type FallbackResolver = (
    name: PackageName,
    framework: StyleFramework | undefined
) => PackageLoader | undefined

/**
 * Maps the optional packages the controller needs to loaders the application declares, so its
 * bundler or import map only ships what the application actually registered.
 */
export class PackageLoaderRegistry {
    private readonly loaders = new Map<PackageName, PackageLoader>()
    private fallback: FallbackResolver | null = null

    register(name: PackageName, loader: PackageLoader): this {
        this.loaders.set(name, loader)

        return this
    }

    has(name: PackageName, framework?: StyleFramework): boolean {
        return this.resolve(name, framework) !== undefined
    }

    load(name: PackageName, framework?: StyleFramework): Promise<unknown> {
        const loader = this.resolve(name, framework)

        if (!loader) {
            return Promise.reject(
                new Error(
                    `The "${name}" package is not registered. Install it and register it with packageLoaders.register('${name}', loader).`
                )
            )
        }

        return loader()
    }

    /** @internal */
    setFallback(fallback: FallbackResolver | null): void {
        this.fallback = fallback
    }

    private resolve(name: PackageName, framework?: StyleFramework): PackageLoader | undefined {
        return this.loaders.get(name) ?? this.fallback?.(name, framework)
    }
}

export const packageLoaders = new PackageLoaderRegistry()

export function defaultExport<T>(module: unknown): T {
    return ((module as { default?: T }).default ?? module) as T
}
