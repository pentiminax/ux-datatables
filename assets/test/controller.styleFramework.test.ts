import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/controller.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

describe('style framework autodetection', () => {
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

    it('warns that autodetection is removed in 2.0 when the payload names no framework', async () => {
        await mountTable({})

        await vi.waitFor(() =>
            expect(warn).toHaveBeenCalledWith(expect.stringContaining('autodetected'))
        )
    })

    it('stays quiet when the payload sets the framework explicitly', async () => {
        await mountTable({ styleFramework: 'dt' })

        await vi.waitFor(() => expect(loadDataTableLibrary).toHaveBeenCalledWith('dt'))
        expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('autodetected'))
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
