import { existsSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import packageJson from '../package.json'

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
    const { controllers } = packageJson.symfony

    it('loads the same module as datatable, with the same autoimports and fetch mode', () => {
        const { enabled: _datatableEnabled, ...datatable } = controllers.datatable
        const { enabled: _aliasEnabled, ...alias } = controllers.datatable_core

        expect(alias).toEqual(datatable)
        expect(existsSync(resolve(process.cwd(), alias.main))).toBe(true)
    })

    it('stays disabled by default, so only one controller registers', () => {
        expect(controllers.datatable.enabled).toBe(true)
        expect(controllers.datatable_core.enabled).toBe(false)
    })
})
