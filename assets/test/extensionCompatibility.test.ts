import type { Api } from 'datatables.net'
import { afterEach, describe, expect, it } from 'vitest'
import { ExtensionRegistry } from '../src/functions/extensionRegistry.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'
import type { StyleFramework } from '../src/types/styleFramework.js'

const frameworks: StyleFramework[] = ['dt', 'bs', 'bs4', 'bs5']
const extensions = [
    { name: 'colReorder', option: 'colReorder', setting: '_colReorder', value: true },
    { name: 'fixedHeader', option: 'fixedHeader', setting: '_fixedHeader', value: true },
    { name: 'keyTable', option: 'keys', setting: 'keytable', value: true },
    { name: 'rowGroup', option: 'rowGroup', setting: 'rowGroup', value: { dataSrc: 'group' } },
    { name: 'scroller', option: 'scroller', setting: 'scroller', value: { rowHeight: 20 } },
]

let table: Api<Record<string, unknown>> | undefined

async function createTable(framework: StyleFramework, options: Record<string, unknown>) {
    const DataTable = await loadDataTableLibrary(framework)
    const element = document.createElement('table')
    // jsdom has no layout; FixedHeader disables itself for zero-size tables.
    Object.defineProperty(element, 'offsetWidth', { value: 300 })
    document.body.append(element)

    table = new DataTable(element, {
        columns: [
            { data: 'group', title: 'Group' },
            { data: 'name', title: 'Name' },
        ],
        data: [
            { group: '<b>Engineering</b>', name: 'Ada' },
            { group: '<b>Engineering</b>', name: 'Grace' },
            { group: 'Sales', name: 'Alex' },
        ],
        order: [[0, 'asc']],
        ...options,
    }) as Api<Record<string, unknown>>

    return table
}

afterEach(() => {
    table?.destroy()
    table = undefined
    document.body.replaceChildren()
})

describe('real DataTables extension compatibility', () => {
    it.each(
        frameworks.flatMap((framework) =>
            extensions.map((extension) => ({
                framework,
                ...extension,
            }))
        )
    )(
        'initializes $name on the active $framework table',
        async ({ framework, name, option, setting, value }) => {
            await loadDataTableLibrary(framework)
            await ExtensionRegistry.load(name, framework)

            const api = await createTable(framework, {
                [option]: value,
                ...(name === 'scroller' ? { scrollY: '200px' } : {}),
            })
            const settings = api.settings()[0] as unknown as Record<string, unknown>

            expect(settings[setting]).toBeDefined()
            expect(api.rows().count()).toBe(3)
            api.search('Ada').draw()
            expect(api.rows({ search: 'applied' }).data().toArray()).toEqual([
                { group: '<b>Engineering</b>', name: 'Ada' },
            ])
        }
    )

    it.each(frameworks)(
        'combines grouping, reordering, fixed headers, and keys on %s',
        async (framework) => {
            await loadDataTableLibrary(framework)
            for (const name of ['colReorder', 'fixedHeader', 'keyTable', 'rowGroup']) {
                await ExtensionRegistry.load(name, framework)
            }

            const api = await createTable(framework, {
                colReorder: true,
                fixedHeader: true,
                keys: true,
                rowGroup: { dataSrc: 'group' },
            })

            api.colReorder.order([1, 0])
            api.search('Grace').draw()

            expect(
                api
                    .columns()
                    .header()
                    .toArray()
                    .map((header) => header.textContent)
            ).toEqual(['Name', 'Group'])
            expect(api.cell(1, 0).data()).toBe('Grace')
            expect(api.fixedHeader.enabled()).toBe(true)
            expect(api.rowGroup().enabled()).toBe(true)
            expect(document.querySelector('tr.dtrg-start')?.textContent).toBe('<b>Engineering</b>')
        }
    )

    it.each(frameworks)('escapes default RowGroup labels on %s', async (framework) => {
        await ExtensionRegistry.load('rowGroup', framework)
        await createTable(framework, { rowGroup: { dataSrc: 'group' } })

        const groupRow = document.querySelector('tr.dtrg-start')
        expect(groupRow?.textContent).toBe('<b>Engineering</b>')
        expect(groupRow?.querySelector('b')).toBeNull()
    })

    it.each(frameworks)('preserves custom RowGroup HTML renderers on %s', async (framework) => {
        await ExtensionRegistry.load('rowGroup', framework)
        await createTable(framework, {
            rowGroup: {
                dataSrc: 'group',
                startRender: () => '<strong>Trusted group heading</strong>',
            },
        })

        expect(document.querySelector('tr.dtrg-start strong')?.textContent).toBe(
            'Trusted group heading'
        )
    })
})
