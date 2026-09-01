import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

vi.mock('../src/functions/detectStyleFramework.js', () => ({
    detectStyleFramework: () => 'dt',
}))

vi.mock('../src/functions/filterFeature.js', () => ({
    registerFilterFeature: vi.fn(),
}))

import DatatableController from '../src/controller.js'

// A browser history restore can hand back the markup DataTables generated on the previous
// document while no live instance exists any more. `render_datatable()` emits an empty
// `<table>`, so anything inside it is stale and must be dropped before re-initializing.
describe('datatable controller history restore', () => {
    let application: Application
    let seen: Array<{ element: Element; childElementCount: number; hadDtClass: boolean }>

    beforeEach(() => {
        seen = []
        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, _payload: unknown) {
            seen.push({
                element,
                childElementCount: element.childElementCount,
                hadDtClass: element.classList.contains('dataTable'),
            })
            initialized.add(element)

            const container = document.createElement('div')
            container.className = 'dt-container'
            element.parentNode?.insertBefore(container, element)
            container.appendChild(element)
            element.classList.add('dataTable')

            return { destroy: vi.fn(), on: vi.fn() }
        }

        MockDataTable.isDataTable = (element: Element) => initialized.has(element)
        MockDataTable.Api = class {
            constructor(_element: Element) {
                return { destroy: vi.fn(), on: vi.fn() }
            }
        }

        vi.mocked(loadDataTableLibrary).mockResolvedValue(MockDataTable)

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('initializes over a bare table untouched', async () => {
        mountTable()
        await settle()

        expect(seen).toHaveLength(1)
        expect(seen[0].childElementCount).toBe(0)
    })

    it('drops generated markup restored without a live instance', async () => {
        const table = mountRestoredTable()
        await settle()

        expect(seen).toHaveLength(1)
        // The stale header cells are what DataTables would otherwise adopt, producing a column
        // count that no longer matches the payload.
        expect(seen[0].childElementCount).toBe(0)
        expect(seen[0].hadDtClass).toBe(false)
        expect(table.querySelector('#stale-row')).toBeNull()
    })

    it('does not reparent the table when a stale wrapper survives', async () => {
        const wrapper = document.createElement('div')
        wrapper.className = 'dt-container'
        document.body.appendChild(wrapper)

        const staleControl = document.createElement('div')
        staleControl.className = 'dt-search'
        staleControl.id = 'stale-search'
        wrapper.appendChild(staleControl)

        const table = mountRestoredTable(wrapper)
        await settle()

        expect(seen).toHaveLength(1)
        expect(seen[0].childElementCount).toBe(0)
        // Reparenting would make Stimulus reconnect and race a second initialization.
        expect(document.querySelector('#stale-search')).toBeNull()
        expect(table.closest('.dt-container')).not.toBeNull()
    })
})

function viewValue(): string {
    return JSON.stringify({
        columns: [
            { data: 'name', title: 'Name' },
            { data: 'email', title: 'Email' },
        ],
    })
}

function mountTable(parent: Element = document.body): HTMLTableElement {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute('data-datatable-view-value', viewValue())
    parent.appendChild(table)
    return table
}

function mountRestoredTable(parent: Element = document.body): HTMLTableElement {
    const table = document.createElement('table')
    table.setAttribute('data-datatable-view-value', viewValue())
    table.className = 'table dataTable'
    table.innerHTML =
        '<thead><tr><th class="dt-orderable-asc">Name</th><th>Email</th></tr></thead>' +
        '<tbody><tr id="stale-row"><td>Ada</td><td>ada@example.com</td></tr></tbody>'
    parent.appendChild(table)
    // Set last so Stimulus connects to markup that is already in the restored state.
    table.setAttribute('data-controller', 'datatable')
    return table
}

async function settle(): Promise<void> {
    for (let i = 0; i < 20; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}
