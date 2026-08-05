import { afterEach, describe, expect, it, vi } from 'vitest'
import { registerFilterFeature } from '../functions/filterFeature.js'
import { applyFilterLayout } from '../functions/filterLayout.js'
import { FilterBar } from '../functions/filters.js'
import { readUrlState, type UrlStateConfig, writeUrlState } from '../functions/urlState.js'
import type { StyleFramework } from '../types/styleFramework.js'

const realBundleFrameworks: StyleFramework[] = ['dt', 'bs5']
const runtimeExtensionImports = [
    () => import('datatables.net-buttons'),
    () => import('datatables.net-colreorder'),
    () => import('datatables.net-columncontrol'),
    () => import('datatables.net-fixedcolumns'),
    () => import('datatables.net-keytable'),
    () => import('datatables.net-responsive'),
    () => import('datatables.net-scroller'),
    () => import('datatables.net-select'),
]

type DataTableFactory = typeof import('datatables.net').default

function createTable(): HTMLTableElement {
    document.body.innerHTML = `
        <table>
            <thead>
                <tr><th>Name</th><th>Status</th></tr>
            </thead>
            <tbody>
                <tr><td>Ada</td><td>Active</td></tr>
                <tr><td>Grace</td><td>Inactive</td></tr>
            </tbody>
        </table>
    `

    return document.querySelector('table') as HTMLTableElement
}

function bindDataTablesGlobals(): void {
    vi.stubGlobal('Event', window.Event)
    vi.stubGlobal('DocumentFragment', window.DocumentFragment)
    vi.stubGlobal('Node', window.Node)
    vi.stubGlobal('Option', window.Option)
    vi.stubGlobal('HTMLElement', window.HTMLElement)
    vi.stubGlobal('HTMLTableElement', window.HTMLTableElement)
}

async function loadCoreDataTable(): Promise<DataTableFactory> {
    bindDataTablesGlobals()

    const { default: dataTableExport } = await import('datatables.net')
    const DataTable = 'isDataTable' in dataTableExport ? dataTableExport : dataTableExport(window)
    ;(window as typeof window & { DataTable: DataTableFactory }).DataTable = DataTable

    return DataTable
}

async function loadFrameworkCss(framework: StyleFramework): Promise<void> {
    if (framework === 'bs5') {
        await import('datatables.net-bs5/css/dataTables.bootstrap5.min.css')

        return
    }

    await import('datatables.net-dt/css/dataTables.dataTables.min.css')
}

async function loadSelectExtension(): Promise<void> {
    const { default: selectExport } = await import('datatables.net-select')

    if (!('isDataTable' in selectExport)) {
        selectExport(window)
    }
}

async function loadButtonsExtension(): Promise<void> {
    const [{ default: buttonsExport }, { default: html5Export }, { default: printExport }] =
        await Promise.all([
            import('datatables.net-buttons'),
            import('datatables.net-buttons/js/buttons.html5'),
            import('datatables.net-buttons/js/buttons.print'),
        ])

    if (!('isDataTable' in buttonsExport)) {
        buttonsExport(window)
    }

    if (typeof html5Export === 'function' && !('Buttons' in html5Export)) {
        html5Export(window)
    }

    if (typeof printExport === 'function' && !('Buttons' in printExport)) {
        printExport(window)
    }
}

describe('DataTables bundle smoke', () => {
    afterEach(() => {
        document.body.innerHTML = ''
        window.history.replaceState(null, '', '/')
        vi.restoreAllMocks()
        vi.unstubAllGlobals()
    })

    it.each(
        realBundleFrameworks
    )('initializes a table with the real %s bundle CSS path', async (framework) => {
        const table = createTable()
        await loadFrameworkCss(framework)

        const DataTable = await loadCoreDataTable()
        const instance = new DataTable(table, {
            data: [
                ['Ada', 'Active'],
                ['Grace', 'Inactive'],
            ],
            destroy: true,
            paging: false,
            searching: false,
        })

        expect(DataTable.isDataTable(table)).toBe(true)
        expect(instance.rows().count()).toBe(2)

        instance.destroy()
    })

    it('imports the real dt integration packages for all extension families', async () => {
        await loadFrameworkCss('dt')
        const DataTable = await loadCoreDataTable()

        for (const loadExtension of runtimeExtensionImports) {
            const mod = await loadExtension()
            expect(mod.default).toBeTruthy()
        }

        expect(typeof DataTable.Buttons).toBe('function')
        expect(typeof DataTable.render.select).toBe('function')
    })

    it.each(
        realBundleFrameworks
    )('initializes Select checkboxes through DataTable.render.select() for %s', async (framework) => {
        const table = createTable()
        await loadFrameworkCss(framework)

        const DataTable = await loadCoreDataTable()
        await loadSelectExtension()

        const instance = new DataTable(table, {
            columnDefs: [
                {
                    orderable: false,
                    render: DataTable.render.select(),
                    targets: 0,
                },
            ],
            data: [
                [null, 'Ada'],
                [null, 'Grace'],
            ],
            destroy: true,
            paging: false,
            searching: false,
            select: {
                selector: 'td:first-child',
                style: 'multi',
            },
        })

        instance.row(0).select()

        expect(table.querySelector('.dt-select-checkbox')).not.toBeNull()
        expect(instance.rows({ selected: true }).count()).toBe(1)

        instance.destroy()
    })

    it('initializes Buttons with csvHtml5 and print controls', async () => {
        const table = createTable()
        await loadFrameworkCss('dt')
        await import('datatables.net-buttons-dt/css/buttons.dataTables.min.css')

        const DataTable = await loadCoreDataTable()
        await loadButtonsExtension()

        const instance = new DataTable(table, {
            data: [
                ['Ada', 'Active'],
                ['Grace', 'Inactive'],
            ],
            destroy: true,
            layout: {
                topStart: {
                    buttons: ['csvHtml5', 'print'],
                },
            },
            paging: false,
            searching: false,
        })

        expect(DataTable.ext.buttons.csvHtml5).toBeTruthy()
        expect(DataTable.ext.buttons.print).toBeTruthy()
        expect(document.querySelector('.buttons-csv')).not.toBeNull()
        expect(document.querySelector('.buttons-print')).not.toBeNull()

        instance.destroy()
    })

    it('renders the real filters feature and reloads after applying a filter', async () => {
        const table = createTable()
        await loadFrameworkCss('dt')

        const DataTable = await loadCoreDataTable()
        registerFilterFeature(DataTable)

        const payload: Record<string, unknown> = {
            filters: [
                {
                    label: 'Status',
                    name: 'status',
                    options: { active: 'Active', inactive: 'Inactive' },
                    type: 'select',
                },
            ],
            layout: { topStart: 'filters' },
        }
        const filterBar = new FilterBar(payload, 'dt')
        applyFilterLayout(payload, filterBar)

        const instance = new DataTable(table, {
            data: [
                ['Ada', 'Active'],
                ['Grace', 'Inactive'],
            ],
            destroy: true,
            layout: payload.layout,
            paging: false,
            searching: false,
        })
        document.querySelector<HTMLButtonElement>('.dt-filters-toggle')?.click()
        const select = document.querySelector<HTMLSelectElement>('select[name="filters[status]"]')
        if (!select) {
            throw new Error('Expected the status filter select to be rendered.')
        }
        select.value = 'active'
        document.querySelector<HTMLButtonElement>('.dt-filters-apply')?.click()

        expect(document.querySelector('.dt-filters-badge')?.textContent).toBe('1')
        expect(filterBar.collectValues()).toEqual({ status: 'active' })

        instance.destroy()
    })

    it('writes and reads real URL state from a DataTables instance', async () => {
        const table = createTable()
        await loadFrameworkCss('dt')
        window.history.replaceState(null, '', '/?existing=1')

        const DataTable = await loadCoreDataTable()
        const instance = new DataTable(table, {
            columns: [{ name: 'name' }, { name: 'status' }],
            data: [
                ['Ada', 'Active'],
                ['Grace', 'Inactive'],
                ['Katherine', 'Active'],
            ],
            destroy: true,
            order: [[1, 'desc']],
            pageLength: 1,
            searching: true,
        })
        const cfg: UrlStateConfig = {
            order: true,
            page: true,
            pageLength: true,
            prefix: 'dt',
            search: true,
        }

        instance.search('a')
        instance.page(1).draw(false)
        writeUrlState(cfg, instance)

        expect(window.location.search).toContain('dt[search]=a')
        expect(readUrlState(cfg)).toEqual({
            order: { dir: 'desc', name: 'status' },
            pageLength: 1,
            search: 'a',
            start: 1,
        })

        instance.destroy()
    })

    it('completes a server-side draw with ajax data from a callback', async () => {
        const table = createTable()
        await loadFrameworkCss('dt')

        const DataTable = await loadCoreDataTable()
        let resolveDraw!: () => void
        const drawCompleted = new Promise<void>((resolve) => {
            resolveDraw = resolve
        })
        const instance = new DataTable(table, {
            ajax: (_data: unknown, callback: (response: unknown) => void) => {
                callback({
                    data: [['Katherine', 'Active']],
                    draw: 1,
                    recordsFiltered: 1,
                    recordsTotal: 1,
                })
            },
            columns: [{ title: 'Name' }, { title: 'Status' }],
            destroy: true,
            drawCallback: () => resolveDraw(),
            paging: false,
            searching: false,
            serverSide: true,
        })

        await drawCompleted

        expect(instance.rows().count()).toBe(1)
        expect(table.querySelector('tbody')?.textContent).toContain('Katherine')

        instance.destroy()
    })
})
