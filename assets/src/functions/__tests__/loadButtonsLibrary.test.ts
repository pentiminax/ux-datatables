import { afterEach, describe, expect, it, vi } from 'vitest'
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
        ['bare buttons feature', { topStart: 'buttons' }, { jszip: true, pdfmake: true }],
        ['no layout', undefined, none],
    ])('%s', (_name, layout, expected) => {
        expect(requiredExportPackages(layout)).toEqual(expected)
    })
})
