import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { StyleFramework } from '../../types/styleFramework.js'
import { requiredExportPackages } from '../loadButtonsLibrary.js'

const frameworks: Array<{ framework: StyleFramework; cssSuffix: string }> = [
    { framework: 'dt', cssSuffix: 'dataTables' },
    { framework: 'bs', cssSuffix: 'bootstrap' },
    { framework: 'bs4', cssSuffix: 'bootstrap4' },
    { framework: 'bs5', cssSuffix: 'bootstrap5' },
]

const staticSpecifiers = [
    'jszip',
    'pdfmake',
    'pdfmake/build/vfs_fonts.js',
    'datatables.net-buttons',
]

const mockedSpecifiers = new Set<string>()

function mockSpecifier(specifier: string, loaded: string[]) {
    mockedSpecifiers.add(specifier)
    vi.doMock(specifier, () => {
        loaded.push(specifier)

        return { default: {} }
    })
}

describe('loadButtonsLibrary', () => {
    afterEach(() => {
        vi.resetModules()

        for (const specifier of mockedSpecifiers) {
            vi.doUnmock(specifier)
        }

        mockedSpecifiers.clear()
    })

    it.each(frameworks)(
        'loads the Buttons JS integration and CSS file for $framework',
        async ({ framework, cssSuffix }) => {
            const loaded: string[] = []
            const frameworkSpecifier = `datatables.net-buttons-${framework}`
            const cssSpecifier = `datatables.net-buttons-${framework}/css/buttons.${cssSuffix}.min.css`
            const DataTable = {
                Buttons: {
                    jszip: vi.fn(),
                    pdfMake: vi.fn(),
                },
            }

            for (const specifier of staticSpecifiers) {
                mockSpecifier(specifier, loaded)
            }

            mockSpecifier(frameworkSpecifier, loaded)
            mockSpecifier(cssSpecifier, loaded)

            await import('../builtinPackageLoaders.js')
            const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')

            await loadButtonsLibrary(
                DataTable as unknown as typeof import('datatables.net').default,
                framework,
                { topStart: { buttons: ['excel', 'pdf'] } }
            )

            expect(loaded).toContain(frameworkSpecifier)
            expect(loaded).toContain(cssSpecifier)
        }
    )

    it('loads jszip but not pdfmake for an Excel-only layout', async () => {
        const loaded: string[] = []
        const DataTable = { Buttons: { jszip: vi.fn(), pdfMake: vi.fn() } }

        for (const specifier of [
            ...staticSpecifiers,
            'datatables.net-buttons-dt',
            'datatables.net-buttons-dt/css/buttons.dataTables.min.css',
        ]) {
            mockSpecifier(specifier, loaded)
        }

        await import('../builtinPackageLoaders.js')
        const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')

        await loadButtonsLibrary(DataTable as never, 'dt', { topStart: { buttons: ['excel'] } })

        expect(loaded).toContain('jszip')
        expect(loaded).not.toContain('pdfmake')
        expect(DataTable.Buttons.jszip).toHaveBeenCalled()
        expect(DataTable.Buttons.pdfMake).not.toHaveBeenCalled()
    })

    describe('optional export packages', () => {
        const buttonsLayout = { topStart: { buttons: ['excel', 'pdf'] } }
        let warn: ReturnType<typeof vi.spyOn>

        beforeEach(() => {
            warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        })

        afterEach(() => {
            warn.mockRestore()
        })

        it('warns which package is missing when the layout needs it and it is not registered', async () => {
            const { packageLoaders } = await import('../packageLoaders.js')
            const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')
            const DataTable = { Buttons: { jszip: vi.fn(), pdfMake: vi.fn() } }

            packageLoaders.register('buttons', async () => ({}))

            await loadButtonsLibrary(DataTable as never, 'dt', buttonsLayout)

            expect(warn).toHaveBeenCalledTimes(2)
            expect(warn.mock.calls[0][0]).toContain('"jszip" package is not registered')
            expect(warn.mock.calls[1][0]).toContain('"pdfmake" package is not registered')
        })

        it('stays quiet when the layout needs neither package', async () => {
            const { packageLoaders } = await import('../packageLoaders.js')
            const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')

            packageLoaders.register('buttons', async () => ({}))

            await loadButtonsLibrary({ Buttons: {} } as never, 'dt', {
                topStart: { buttons: ['copy'] },
            })

            expect(warn).not.toHaveBeenCalled()
        })

        it('disables only the button whose package fails to load', async () => {
            const { packageLoaders } = await import('../packageLoaders.js')
            const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')
            const DataTable = { Buttons: { jszip: vi.fn(), pdfMake: vi.fn() } }
            const failure = new Error('chunk failed')

            packageLoaders.register('buttons', async () => ({}))
            packageLoaders.register('jszip', async () => {
                throw failure
            })
            packageLoaders.register('pdfmake', async () => ({ default: {} }))

            await loadButtonsLibrary(DataTable as never, 'dt', buttonsLayout)

            expect(DataTable.Buttons.jszip).not.toHaveBeenCalled()
            expect(DataTable.Buttons.pdfMake).toHaveBeenCalledTimes(1)
            expect(warn).toHaveBeenCalledTimes(1)
            expect(warn.mock.calls[0][0]).toContain('"jszip" package failed to load')
            expect(warn.mock.calls[0][1]).toBe(failure)
        })

        it('still fails loudly when the Buttons package itself is missing', async () => {
            const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')

            await expect(
                loadButtonsLibrary({ Buttons: {} } as never, 'dt', buttonsLayout)
            ).rejects.toThrow('The "buttons" package is not registered.')
        })
    })
})

describe('requiredExportPackages', () => {
    const none = { jszip: false, pdfmake: false }

    it.each([
        [
            'excel only',
            { topStart: { buttons: ['copy', 'excel'] } },
            { jszip: true, pdfmake: false },
        ],
        ['pdf only', { topStart: { buttons: ['pdf'] } }, { jszip: false, pdfmake: true }],
        [
            'extend objects',
            { topStart: [{ buttons: [{ extend: 'excelHtml5' }, { extend: 'pdfHtml5' }] }] },
            { jszip: true, pdfmake: true },
        ],
        [
            'nested collection',
            {
                topEnd: {
                    buttons: [
                        { extend: 'collection', buttons: ['csv', { extend: 'pdf' }] },
                        { extend: 'collection', postfixButtons: ['excel'] },
                    ],
                },
            },
            { jszip: true, pdfmake: true },
        ],
        [
            'server export only',
            {
                topStart: {
                    buttons: [
                        { action: 'export', format: 'xlsx', className: 'dt-export-excel' },
                        'csv',
                    ],
                },
            },
            none,
        ],
        [
            'split dropdown',
            { topStart: { buttons: [{ extend: 'csv', split: ['excel', 'pdf'] }] } },
            { jszip: true, pdfmake: true },
        ],
        ['bare buttons feature', { topStart: 'buttons' }, { jszip: true, pdfmake: true }],
        ['no layout', undefined, none],
    ])('%s', (_name, layout, expected) => {
        expect(requiredExportPackages(layout)).toEqual(expected)
    })
})
