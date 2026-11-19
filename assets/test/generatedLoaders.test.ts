import { beforeEach, describe, expect, it, vi } from 'vitest'

// The files are rendered by PackageCatalog (see PackageCatalogTest), as ux-datatables:install writes them.
const kits = [
    { framework: 'dt', file: () => import('./fixtures/install/generated-dt.js') },
    { framework: 'bs5', file: () => import('./fixtures/install/generated-bs5.js') },
] as const

describe.each(kits)('generated $framework loaders', ({ framework, file }) => {
    beforeEach(() => {
        // test/setup.ts fills the shared registry, so start from a fresh module graph.
        vi.resetModules()
    })

    it('register the packages for their framework and load them', async () => {
        const { packageLoaders } = await import('../src/core.js')
        await file()

        expect(packageLoaders.frameworksOf('dataTables')).toEqual([framework])
        expect(packageLoaders.has('dataTables', framework)).toBe(true)
        expect(packageLoaders.has('select', framework)).toBe(true)
        expect(packageLoaders.isRegistered('select')).toBe(true)

        const DataTable = (await packageLoaders.load('dataTables', framework)) as {
            default: unknown
        }
        expect(typeof (DataTable.default ?? DataTable)).toBe('function')

        await expect(packageLoaders.load('select', framework)).resolves.not.toThrow()
        await expect(packageLoaders.load('searchBuilder', framework)).resolves.not.toThrow()
    })

    it('leave another style without a loader', async () => {
        const { packageLoaders } = await import('../src/core.js')
        await file()
        const other = framework === 'dt' ? 'bs5' : 'dt'

        expect(packageLoaders.has('dataTables', other)).toBe(false)
        await expect(packageLoaders.load('dataTables', other)).rejects.toThrow(
            'The "dataTables" package is not registered.'
        )
    })

    it('copy the Bootstrap modal adapter for bs5 only', async () => {
        const { modalAdapters } = await import('../src/core.js')
        await file()

        expect(modalAdapters.get('bs5') !== null).toBe(framework === 'bs5')
    })
})

describe('datatable_core alias', () => {
    it('is the same controller class the datatable identifier registers', async () => {
        const { default: Controller } = await import('../src/core.js')
        const { Application } = await import('@hotwired/stimulus')
        const app = Application.start()

        app.register('datatable', Controller)
        app.register('datatable_core', Controller)

        const modules = app.router.modules
        expect(modules.map((module) => module.identifier)).toEqual(['datatable', 'datatable_core'])
        expect(modules[1].definition.controllerConstructor.prototype).toBeInstanceOf(
            modules[0].definition.controllerConstructor.__proto__
        )
        app.stop()
    })
})
