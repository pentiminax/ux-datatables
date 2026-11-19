import { afterEach, describe, expect, it, vi } from 'vitest'

describe('packageLoaders', () => {
    afterEach(() => {
        vi.restoreAllMocks()
        vi.resetModules()
    })

    it('rejects an unregistered package with an actionable message when nothing is registered', async () => {
        const { PackageLoaderRegistry } = await import('../packageLoaders.js')

        await expect(new PackageLoaderRegistry().load('select', 'bs5')).rejects.toThrow(
            `The "select" package is not registered. Install it and register it with packageLoaders.register('select', loader).`
        )
    })

    it('names the style and the third argument when only another style has a loader', async () => {
        const { PackageLoaderRegistry } = await import('../packageLoaders.js')
        const registry = new PackageLoaderRegistry().register('select', async () => 'dt', 'dt')

        await expect(registry.load('select', 'bs5')).rejects.toThrow(
            `registered only for the dt style, not for "bs5": register it for "bs5" with packageLoaders.register('select', loader, 'bs5')`
        )
    })

    it('rejects a loader registered under an unknown name and lists the valid ones', async () => {
        const { PackageLoaderRegistry } = await import('../packageLoaders.js')
        const registry = new PackageLoaderRegistry()

        expect(() => registry.register('selct' as never, async () => ({}))).toThrow(
            'Cannot register an unknown package "selct". Valid names: dataTables, buttons, jszip, pdfmake, bootstrap, lucide, colReorder'
        )
        expect(registry.has('selct' as never)).toBe(false)
    })

    it('prefers a loader registered for the framework over the one registered for the name', async () => {
        const { PackageLoaderRegistry } = await import('../packageLoaders.js')
        const registry = new PackageLoaderRegistry()
            .register('select', async () => 'any')
            .register('select', async () => 'bootstrap', 'bs5')

        await expect(registry.load('select', 'bs5')).resolves.toBe('bootstrap')
        await expect(registry.load('select', 'dt')).resolves.toBe('any')
        await expect(registry.load('select')).resolves.toBe('any')
    })

    it('skips JSZip and pdfmake when the application did not register them', async () => {
        const { packageLoaders } = await import('../packageLoaders.js')
        const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')
        const DataTable = { Buttons: { jszip: vi.fn(), pdfMake: vi.fn() } }

        packageLoaders.register('buttons', async () => ({}))

        await loadButtonsLibrary(DataTable as never, 'bs5', {
            topStart: { buttons: ['excel', 'pdf'] },
        })

        expect(DataTable.Buttons.jszip).not.toHaveBeenCalled()
        expect(DataTable.Buttons.pdfMake).not.toHaveBeenCalled()
    })
})
