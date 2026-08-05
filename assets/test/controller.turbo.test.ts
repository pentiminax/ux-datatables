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

type MockInstance = {
    destroy: ReturnType<typeof vi.fn>
    on: ReturnType<typeof vi.fn>
}

describe('datatable controller Turbo lifecycle', () => {
    let application: Application
    let loadResolvers: Array<(value: unknown) => void>
    let instances: MockInstance[]
    let initialized: WeakSet<Element>
    let MockDataTable: ReturnType<typeof createMockDataTable>

    beforeEach(() => {
        loadResolvers = []
        instances = []
        initialized = new WeakSet()
        MockDataTable = createMockDataTable(initialized, instances)

        vi.mocked(loadDataTableLibrary).mockImplementation(
            () =>
                new Promise((resolve) => {
                    loadResolvers.push(resolve)
                })
        )

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('calls destroy on disconnect after init', async () => {
        const table = mountTable(application)
        const controller = await getController(application, table)

        expect(loadResolvers).toHaveLength(1)
        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        expect(instances).toHaveLength(1)

        controller.disconnect()

        expect(instances[0].destroy).toHaveBeenCalledTimes(1)
    })

    it('ignores a stale connect after Turbo disconnect mid-load', async () => {
        const table = mountTable(application)
        const controller = await getController(application, table)

        expect(loadResolvers).toHaveLength(1)

        controller.disconnect()
        void controller.connect()
        await flushMicrotasks()

        expect(loadResolvers).toHaveLength(2)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()
        expect(instances).toHaveLength(0)

        loadResolvers[1](MockDataTable)
        await flushMicrotasks()
        expect(instances).toHaveLength(1)
    })

    it('destroys then reinits on reconnect', async () => {
        const table = mountTable(application)
        const controller = await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()
        expect(instances).toHaveLength(1)

        controller.disconnect()
        expect(instances[0].destroy).toHaveBeenCalledTimes(1)

        void controller.connect()
        await flushMicrotasks()
        expect(loadResolvers).toHaveLength(2)

        loadResolvers[1](MockDataTable)
        await flushMicrotasks()

        expect(instances).toHaveLength(2)
        expect(instances[1].destroy).not.toHaveBeenCalled()
    })
})

function createMockDataTable(initialized: WeakSet<Element>, instances: MockInstance[]) {
    function MockDataTable(this: void, element: Element, _payload: unknown) {
        initialized.add(element)
        const instance: MockInstance = {
            destroy: vi.fn(() => {
                initialized.delete(element)
            }),
            on: vi.fn(),
        }
        instances.push(instance)
        return instance
    }

    MockDataTable.isDataTable = (element: Element) => initialized.has(element)
    MockDataTable.Api = class {
        constructor(private readonly element: Element) {}

        destroy(): void {
            initialized.delete(this.element)
        }
    }

    return MockDataTable
}

function mountTable(application: Application): HTMLTableElement {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute(
        'data-datatable-view-value',
        JSON.stringify({
            columns: [{ data: 'name', title: 'Name' }],
        })
    )
    document.body.appendChild(table)
    return table
}

async function getController(
    application: Application,
    table: HTMLTableElement
): Promise<InstanceType<typeof DatatableController>> {
    await flushMicrotasks()

    const controller = application.getControllerForElementAndIdentifier(table, 'datatable')
    if (!controller) {
        throw new Error('Controller not connected')
    }

    return controller as InstanceType<typeof DatatableController>
}

async function flushMicrotasks(): Promise<void> {
    await Promise.resolve()
    await Promise.resolve()
    await Promise.resolve()
}
