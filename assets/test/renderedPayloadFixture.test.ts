import { Application } from '@hotwired/stimulus'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/controller.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'
import { packageLoaders } from '../src/functions/packageLoaders.js'

/**
 * Feeds the controller the payload the PHP suite pins in
 * tests/Unit/Twig/RenderedPayloadFixtureTest.php. A PHP-side payload change that the frontend no
 * longer reads fails here instead of in a browser.
 */
vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

const fixture = JSON.parse(
    // Vitest runs from assets/, so the PHP fixture sits one directory up.
    readFileSync(resolve(process.cwd(), '../tests/Fixtures/rendered-payload.json'), 'utf8')
)

describe('rendered payload fixture', () => {
    let application: Application
    let payloads: Array<Record<string, any>>
    let registeredFeatures: string[]
    let warn: ReturnType<typeof vi.spyOn>

    beforeEach(() => {
        payloads = []
        registeredFeatures = []
        warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        packageLoaders.setFallback(null)
        packageLoaders.register('buttons', async () => ({}))
        for (const name of ['select', 'responsive']) {
            packageLoaders.register(name, async () => ({}))
        }

        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, payload: Record<string, any>) {
            payloads.push(payload)
            initialized.add(element)

            return { destroy: vi.fn(), on: vi.fn(), ajax: { reload: vi.fn() } }
        }

        MockDataTable.feature = {
            register: vi.fn((name: string) => registeredFeatures.push(name)),
        }
        MockDataTable.render = { select: vi.fn(() => ({})) }
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

    it('initializes a table from the pinned payload and keeps its sections', async () => {
        const table = document.createElement('table')
        table.setAttribute('data-controller', 'datatable')
        table.setAttribute('data-datatable-view-value', JSON.stringify(fixture))
        document.body.appendChild(table)

        await vi.waitFor(() => expect(payloads).toHaveLength(1))

        const payload = payloads[0]
        const dataKeys = fixture.columns.map((column: { data: string }) => column.data)

        expect(payload.columns.map((column: { data: string }) => column.data)).toEqual(
            expect.arrayContaining(dataKeys)
        )
        expect(payload.data).toEqual(fixture.data)
        expect(payload.select).toBeTruthy()
        expect(Object.keys(fixture)).toEqual(
            expect.arrayContaining(['filters', 'bulkActions', 'mercure', 'layout', 'responsive'])
        )
        expect(registeredFeatures.length).toBeGreaterThan(0)
    })
})
