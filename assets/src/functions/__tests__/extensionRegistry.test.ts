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

const extensionFamilies = [
    { name: 'buttons', packageKey: 'buttons', fileBase: 'buttons' },
    { name: 'colReorder', packageKey: 'colreorder', fileBase: 'colReorder' },
    { name: 'columnControl', packageKey: 'columncontrol', fileBase: 'columnControl' },
    { name: 'fixedColumns', packageKey: 'fixedcolumns', fileBase: 'fixedColumns' },
    { name: 'keyTable', packageKey: 'keytable', fileBase: 'keyTable' },
    { name: 'responsive', packageKey: 'responsive', fileBase: 'responsive' },
    { name: 'rowGroup', packageKey: 'rowgroup', fileBase: 'rowGroup' },
    { name: 'scroller', packageKey: 'scroller', fileBase: 'scroller' },
    { name: 'select', packageKey: 'select', fileBase: 'select' },
]

const registryExtensions = extensionFamilies.filter(({ name }) => name !== 'buttons')
const mockedSpecifiers = new Set<string>()

function specifiersFor(
    packageKey: string,
    fileBase: string,
    framework: StyleFramework,
    cssSuffix: string
) {
    return {
        css: `datatables.net-${packageKey}-${framework}/css/${fileBase}.${cssSuffix}.min.css`,
        js: `datatables.net-${packageKey}-${framework}`,
    }
}

function mockSpecifier(specifier: string, loaded: string[]) {
    mockedSpecifiers.add(specifier)
    vi.doMock(specifier, () => {
        loaded.push(specifier)

        return {}
    })
}

describe('DataTables extension package resolution', () => {
    afterEach(() => {
        vi.resetModules()

        for (const specifier of mockedSpecifiers) {
            vi.doUnmock(specifier)
        }

        mockedSpecifiers.clear()
    })

    it.each(
        frameworks.flatMap(({ framework, cssSuffix }) =>
            extensionFamilies.map(({ name, packageKey, fileBase }) => ({
                cssSuffix,
                fileBase,
                framework,
                name,
                packageKey,
            }))
        )
    )('resolves real $name JS and CSS files for $framework', ({
        framework,
        cssSuffix,
        packageKey,
        fileBase,
    }) => {
        const { js, css } = specifiersFor(packageKey, fileBase, framework, cssSuffix)

        expect(import.meta.resolve(js)).toContain(`/node_modules/${js}/`)
        expect(import.meta.resolve(css)).toContain(
            `/node_modules/datatables.net-${packageKey}-${framework}/css/`
        )
    })

    it.each(
        registryExtensions.flatMap(({ name, packageKey, fileBase }) =>
            frameworks.map(({ framework, cssSuffix }) => ({
                cssSuffix,
                fileBase,
                framework,
                name,
                packageKey,
            }))
        )
    )('loads JS and CSS specifiers for $name on $framework', async ({
        framework,
        cssSuffix,
        name,
        packageKey,
        fileBase,
    }) => {
        const loaded: string[] = []
        const { js, css } = specifiersFor(packageKey, fileBase, framework, cssSuffix)

        mockSpecifier(js, loaded)
        mockSpecifier(css, loaded)

        const { ExtensionRegistry } = await import('../extensionRegistry.js')

        await ExtensionRegistry.load(name, framework)

        expect(loaded).toEqual([js, css])
    })

    it('rejects unknown extension names before importing a bundle', async () => {
        const { ExtensionRegistry } = await import('../extensionRegistry.js')

        await expect(ExtensionRegistry.load('missing', 'dt')).rejects.toThrow(
            'Unknown extension: "missing"'
        )
    })
})
