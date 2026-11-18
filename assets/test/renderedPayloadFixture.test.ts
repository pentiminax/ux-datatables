import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DatatableController from '../src/controller.js'
import frFR from '../src/i18n/fr-FR.js'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'
import { packageLoaders } from '../src/functions/packageLoaders.js'
import { type RowActionName, rowActionUrl } from '../src/functions/rowActionUrl.js'
import type { DataTableView } from '../src/types/dataTableView.js'
import fixture from '../../tests/Fixtures/rendered-payload.json'

/**
 * Feeds the controller the payload the PHP suite pins in
 * tests/Unit/Twig/RenderedPayloadFixtureTest.php. A PHP-side payload change that the frontend no
 * longer reads fails here instead of in a browser.
 */
vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

// `npm run typecheck` compiles this file: a fixture key the interface does not declare, a declared
// key the fixture lost, or a value of the wrong type fails it.
type DeclaredKey = keyof {
    [Key in keyof DataTableView as string extends Key ? never : Key]: unknown
}
// DataTables.net extensions the fixture table enables; the view passes them through untyped.
type PassthroughKey = 'responsive' | 'select'
// Bundle keys the fixture table does not set.
type UnexercisedKey =
    | 'apiPlatform'
    | 'apiPlatformServerSide'
    | 'csrfToken'
    | 'emptyState'
    | 'exportUrl'
    | 'forwardedQuery'
    | 'highlight'
    | 'locale'
    | 'pollInterval'
    | 'recordUrl'
    | 'serverSide'
    | 'showHeaderResetButton'
    | 'styleFramework'
    | 'summaryLabels'
    | 'urlState'
type FixtureKey = keyof typeof fixture
type Undeclared = Exclude<FixtureKey, DeclaredKey | PassthroughKey>
type Unpinned = Exclude<DeclaredKey, FixtureKey | UnexercisedKey>

const everyFixtureKeyDeclared: [Undeclared] extends [never] ? true : Undeclared = true
const everyDeclaredKeyPinned: [Unpinned] extends [never] ? true : Unpinned = true
// JSON modules widen string literals, so the filter `type` union is restored by hand.
const view: DataTableView = {
    ...fixture,
    filters: fixture.filters as DataTableView['filters'],
}

describe('rendered payload fixture', () => {
    let application: Application
    let payloads: DataTableView[]
    let registeredFeatures: string[]
    let warn: ReturnType<typeof vi.spyOn>

    beforeEach(() => {
        payloads = []
        registeredFeatures = []
        warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        packageLoaders.setFallback(null)
        packageLoaders.register('buttons', async () => ({}))
        for (const name of ['select', 'responsive'] as const) {
            packageLoaders.register(name, async () => ({}))
        }

        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, payload: DataTableView) {
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

    it('declares every key of the pinned payload in the view interface', () => {
        expect(everyFixtureKeyDeclared).toBe(true)
        expect(everyDeclaredKeyPinned).toBe(true)
        expect(view).toEqual(fixture)
    })

    it('initializes a table from the pinned payload and keeps its sections', async () => {
        const table = document.createElement('table')
        table.setAttribute('data-controller', 'datatable')
        table.setAttribute('data-datatable-view-value', JSON.stringify(view))
        document.body.appendChild(table)

        await vi.waitFor(() => expect(payloads).toHaveLength(1))

        const payload = payloads[0]
        const dataKeys = fixture.columns.map((column: { data: string }) => column.data)

        expect(payload.columns.map((column) => column.data)).toEqual(
            expect.arrayContaining(dataKeys)
        )
        expect(payload.data).toEqual(fixture.data)
        expect(payload.select).toBeTruthy()
        expect(Object.keys(fixture)).toEqual(
            expect.arrayContaining(['filters', 'bulkActions', 'mercure', 'layout', 'responsive'])
        )
        expect(registeredFeatures.length).toBeGreaterThan(0)
    })

    it('renders the CUSTOM action, loads the bundled catalog and reads the row action URLs', async () => {
        const table = document.createElement('table')
        table.setAttribute('data-controller', 'datatable')
        table.setAttribute('data-datatable-view-value', JSON.stringify(view))
        document.body.appendChild(table)

        await vi.waitFor(() => expect(payloads).toHaveLength(1))

        const payload = payloads[0]
        const render = payload.columns.find((column) => column.actions)?.render
        if (typeof render !== 'function') {
            throw new Error('The actions column has no renderer.')
        }
        const html = render(null, 'display', fixture.data[0])

        expect(html).toContain('data-action-type="CUSTOM"')
        expect(html).toContain('href="/books/archive"')

        expect(payload.language).toEqual(frFR)
        expect(fixture.language.url).toMatch(/\/i18n\/fr-FR\.json$/)

        const names: RowActionName[] = ['delete', 'toggle', 'detail', 'editForm', 'editFormSubmit']
        expect(Object.keys(fixture.rowActionUrls)).toEqual(names)
        for (const name of names) {
            expect(rowActionUrl(payload, name)).toBe(fixture.rowActionUrls[name])
        }
    })
})
