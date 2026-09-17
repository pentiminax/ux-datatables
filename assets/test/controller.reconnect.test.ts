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

describe('datatable controller reconnect event', () => {
    let application: Application
    let initCount: number
    let connectEvents: CustomEvent[]
    let reconnectEvents: CustomEvent[]

    const collectConnect = (event: Event) => connectEvents.push(event as CustomEvent)
    const collectReconnect = (event: Event) => reconnectEvents.push(event as CustomEvent)

    beforeEach(() => {
        initCount = 0
        connectEvents = []
        reconnectEvents = []

        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, _payload: unknown) {
            initCount++
            initialized.add(element)

            // DataTables wraps the table in div.dt-container, which reparents the element and makes
            // Stimulus tear the controller down and build a fresh one.
            const container = document.createElement('div')
            container.className = 'dt-container'
            element.parentNode?.insertBefore(container, element)
            container.appendChild(element)

            return {
                destroy: vi.fn(() => {
                    initialized.delete(element)
                    container.parentNode?.insertBefore(element, container)
                    container.remove()
                }),
                on: vi.fn(),
            }
        }

        MockDataTable.isDataTable = (element: Element) => initialized.has(element)
        MockDataTable.Api = class {
            constructor(private readonly element: Element) {}

            destroy(): void {
                initialized.delete(this.element)
            }
        }

        vi.mocked(loadDataTableLibrary).mockResolvedValue(MockDataTable)

        document.addEventListener('datatables:connect', collectConnect)
        document.addEventListener('datatables:reconnect', collectReconnect)

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        document.removeEventListener('datatables:connect', collectConnect)
        document.removeEventListener('datatables:reconnect', collectReconnect)
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('dispatches reconnect once when DataTables reparents the table element', async () => {
        mountTable()

        await settle()

        expect(initCount).toBe(1)
        expect(connectEvents).toHaveLength(1)
        expect(reconnectEvents).toHaveLength(1)
        expect(reconnectEvents[0].detail.table).toBeTruthy()
    })

    it('does not dispatch reconnect once the table has been destroyed for a Turbo snapshot', async () => {
        mountTable()

        await settle()

        reconnectEvents.length = 0

        document.dispatchEvent(new Event('turbo:before-cache'))

        await settle()

        expect(reconnectEvents).toHaveLength(0)
        expect(initCount).toBe(1)
    })
})

function mountTable(): HTMLTableElement {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute(
        'data-datatable-view-value',
        JSON.stringify({ columns: [{ data: 'name', title: 'Name' }] })
    )
    document.body.appendChild(table)

    return table
}

async function settle(): Promise<void> {
    for (let i = 0; i < 20; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}
