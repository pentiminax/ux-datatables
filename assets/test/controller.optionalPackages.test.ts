import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/controller.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'
import { packageLoaders } from '../src/functions/packageLoaders.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

vi.mock('../src/functions/detectStyleFramework.js', () => ({
    detectStyleFramework: () => 'dt',
}))

describe('optional packages', () => {
    let application: Application
    let payloads: Array<Record<string, any>>
    let warn: ReturnType<typeof vi.spyOn>
    let jszip: ReturnType<typeof vi.fn>

    beforeEach(() => {
        payloads = []
        warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        jszip = vi.fn()
        packageLoaders.setFallback(null)
        packageLoaders.register('buttons', async () => ({}))

        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, payload: Record<string, any>) {
            payloads.push(payload)
            initialized.add(element)

            return { destroy: vi.fn(), on: vi.fn(), ajax: { reload: vi.fn() } }
        }

        MockDataTable.feature = { register: vi.fn() }
        MockDataTable.Buttons = { jszip, pdfMake: vi.fn() }
        MockDataTable.isDataTable = (element: Element) => initialized.has(element)

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

    it('initializes a table with an icon column without Lucide and warns', async () => {
        await mountTable({ columns: [{ data: 'icon', customOptions: { isIcon: true } }] })

        await vi.waitFor(() => expect(payloads).toHaveLength(1))
        expect(warn).toHaveBeenCalledWith(
            expect.stringContaining('The "lucide" package is not registered')
        )
    })

    it('initializes the table without the Excel button when JSZip is not registered', async () => {
        await mountTable({ layout: { topStart: { buttons: ['excel'] } } })

        await vi.waitFor(() => expect(payloads).toHaveLength(1))
        expect(jszip).not.toHaveBeenCalled()
        expect(warn).toHaveBeenCalledWith(
            expect.stringContaining('The "jszip" package is not registered')
        )
    })

    it('initializes the table without the Excel button when JSZip fails to load', async () => {
        packageLoaders.register('jszip', async () => {
            throw new Error('chunk failed')
        })

        await mountTable({ layout: { topStart: { buttons: ['excel'] } } })

        await vi.waitFor(() => expect(payloads).toHaveLength(1))
        expect(jszip).not.toHaveBeenCalled()
        expect(warn).toHaveBeenCalledWith(
            expect.stringContaining('The "jszip" package failed to load'),
            expect.any(Error)
        )
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
