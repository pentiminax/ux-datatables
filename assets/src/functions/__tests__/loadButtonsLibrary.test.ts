import { afterEach, describe, expect, it, vi } from 'vitest'
import type { StyleFramework } from '../../types/styleFramework.js'

const frameworks: Array<{ framework: StyleFramework; cssSuffix: string }> = [
    { framework: 'dt', cssSuffix: 'dataTables' },
    { framework: 'bs', cssSuffix: 'bootstrap' },
    { framework: 'bs4', cssSuffix: 'bootstrap4' },
    { framework: 'bs5', cssSuffix: 'bootstrap5' },
    { framework: 'zf', cssSuffix: 'foundation' },
    { framework: 'jqui', cssSuffix: 'jqueryui' },
    { framework: 'se', cssSuffix: 'semanticui' },
]

const staticSpecifiers = [
    'jszip',
    'pdfmake',
    'pdfmake/build/vfs_fonts',
    'datatables.net-buttons',
    'datatables.net-buttons/js/buttons.colVis',
    'datatables.net-buttons/js/buttons.html5',
    'datatables.net-buttons/js/buttons.print',
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

    it.each(frameworks)('loads the Buttons JS integration and CSS file for $framework', async ({
        framework,
        cssSuffix,
    }) => {
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

        const { loadButtonsLibrary } = await import('../loadButtonsLibrary.js')

        await loadButtonsLibrary(
            DataTable as unknown as typeof import('datatables.net').default,
            framework
        )

        expect(loaded).toContain(frameworkSpecifier)
        expect(loaded).toContain(cssSpecifier)
    })
})
