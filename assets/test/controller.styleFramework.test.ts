import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/coreController.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

describe('style framework default', () => {
    let application: Application
    let warn: ReturnType<typeof vi.spyOn>

    beforeEach(() => {
        vi.resetModules()
        warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        Object.defineProperty(document, 'styleSheets', {
            configurable: true,
            value: [{ href: '/css/dataTables.dataTables.min.css' }],
        })

        function MockDataTable(this: void) {
            return { destroy: vi.fn(), on: vi.fn(), ajax: { reload: vi.fn() } }
        }

        MockDataTable.feature = { register: vi.fn() }
        MockDataTable.isDataTable = () => false

        vi.mocked(loadDataTableLibrary).mockResolvedValue(MockDataTable)

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        warn.mockRestore()
        vi.clearAllMocks()
    })

    it('uses the framework the payload declares', async () => {
        await mountTable({ styleFramework: 'bs5' })

        await vi.waitFor(() => expect(loadDataTableLibrary).toHaveBeenCalledWith('bs5'))
    })
})

async function mountTable(view: Record<string, unknown>): Promise<void> {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute(
        'data-datatable-view-value',
        JSON.stringify({ columns: [{ data: 'name', title: 'Name' }], ...view })
    )
    document.body.appendChild(table)

    for (let i = 0; i < 10; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}

describe('style framework from the registered loaders', () => {
    it('resolves a payload without styleFramework to the only registered style', async () => {
        vi.resetModules()
        const { packageLoaders } = await import('../src/functions/packageLoaders.js')
        const { loadDataTableLibrary: mocked } = await import(
            '../src/functions/loadDataTableLibrary.js'
        )
        const { default: Controller } = await import('../src/coreController.js')
        packageLoaders.register('dataTables', async () => ({}), 'bs5')
        vi.mocked(mocked).mockResolvedValue(
            Object.assign(function MockDataTable() {}, { feature: { register: vi.fn() }, isDataTable: () => false }) as never
        )

        const application = Application.start()
        application.register('datatable', Controller)
        const table = document.createElement('table')
        table.setAttribute('data-controller', 'datatable')
        table.setAttribute(
            'data-datatable-view-value',
            JSON.stringify({ columns: [{ data: 'name', title: 'Name' }] })
        )
        document.body.appendChild(table)

        await vi.waitFor(() => expect(mocked).toHaveBeenCalledWith('bs5'))
        application.stop()
        document.body.innerHTML = ''
    })
})
