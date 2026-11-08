import { afterEach, describe, expect, it, vi } from 'vitest'

describe('packageLoaders', () => {
    afterEach(() => {
        vi.restoreAllMocks()
        vi.resetModules()
    })

    it('rejects an unregistered package with an actionable message when no fallback exists', async () => {
        const { PackageLoaderRegistry } = await import('../packageLoaders.js')

        await expect(new PackageLoaderRegistry().load('select', 'bs5')).rejects.toThrow(
            `The "select" package is not registered. Install it and register it with packageLoaders.register('select', loader).`
        )
    })

    it('prefers the registered loader over the built-in fallback', async () => {
        const { packageLoaders } = await import('../packageLoaders.js')
        await import('../builtinPackageLoaders.js')
        const select = { name: 'registered select' }

        packageLoaders.register('select', async () => select)

        await expect(packageLoaders.load('select', 'bs5')).resolves.toBe(select)
    })

    it('loads a package through the built-in fallback when nothing is registered', async () => {
        const jszip = { default: {} }
        vi.doMock('jszip', () => jszip)
        const { packageLoaders } = await import('../packageLoaders.js')
        await import('../builtinPackageLoaders.js')

        await expect(packageLoaders.load('jszip')).resolves.toBeDefined()
        vi.doUnmock('jszip')
    })

    it('skips JSZip and pdfmake when the application did not register them', async () => {
        const { packageLoaders } = await import('../packageLoaders.js')
        const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')
        const DataTable = { Buttons: { jszip: vi.fn(), pdfMake: vi.fn() } }

        packageLoaders.setFallback(null)
        packageLoaders.register('buttons', async () => ({}))

        await loadButtonsLibrary(DataTable as never, 'bs5', {
            topStart: { buttons: ['excel', 'pdf'] },
        })

        expect(DataTable.Buttons.jszip).not.toHaveBeenCalled()
        expect(DataTable.Buttons.pdfMake).not.toHaveBeenCalled()
    })
})
