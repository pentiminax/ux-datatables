import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadDataTableLibrary } from '../src/functions/loadDataTableLibrary.js'
import { createMercureSubscription } from '../src/functions/mercureSubscription.js'

vi.mock('../src/functions/loadDataTableLibrary.js', () => ({
    loadDataTableLibrary: vi.fn(),
}))

vi.mock('../src/functions/detectStyleFramework.js', () => ({
    detectStyleFramework: () => 'dt',
}))

vi.mock('../src/functions/filterFeature.js', () => ({
    registerFilterFeature: vi.fn(),
}))

vi.mock('../src/functions/mercureSubscription.js', () => ({
    createMercureSubscription: vi.fn(),
}))

import DatatableController from '../src/controller.js'

type MockInstance = {
    destroy: ReturnType<typeof vi.fn>
    on: ReturnType<typeof vi.fn>
}

type MockEventSource = {
    close: ReturnType<typeof vi.fn>
}

describe('datatable controller Turbo lifecycle', () => {
    let application: Application
    let loadResolvers: Array<(value: unknown) => void>
    let instances: MockInstance[]
    let initialized: WeakSet<Element>
    let apiDestroyCalls: number
    let MockDataTable: ReturnType<typeof createMockDataTable>

    beforeEach(() => {
        loadResolvers = []
        instances = []
        initialized = new WeakSet()
        apiDestroyCalls = 0
        MockDataTable = createMockDataTable(initialized, instances, () => {
            apiDestroyCalls++
        })

        vi.mocked(loadDataTableLibrary).mockImplementation(
            () =>
                new Promise((resolve) => {
                    loadResolvers.push(resolve)
                })
        )

        vi.mocked(createMercureSubscription).mockImplementation(() => {
            return { close: vi.fn() } as unknown as EventSource
        })

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('calls destroy on disconnect after init', async () => {
        const table = mountTable()
        const controller = await getController(application, table)

        expect(loadResolvers).toHaveLength(1)
        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        expect(instances).toHaveLength(1)

        controller.disconnect()

        expect(instances[0].destroy).toHaveBeenCalledTimes(1)
    })

    it('ignores a stale connect after Turbo disconnect mid-load', async () => {
        const table = mountTable()
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
        const table = mountTable()
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

    it('does not let a stale connect destroy a newer DataTable instance', async () => {
        const table = mountTable()
        const controller = await getController(application, table)

        expect(loadResolvers).toHaveLength(1)

        controller.disconnect()
        void controller.connect()
        await flushMicrotasks()

        expect(loadResolvers).toHaveLength(2)

        loadResolvers[1](MockDataTable)
        await flushMicrotasks()
        expect(instances).toHaveLength(1)
        expect(MockDataTable.isDataTable(table)).toBe(true)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        expect(instances).toHaveLength(1)
        expect(instances[0].destroy).not.toHaveBeenCalled()
        expect(apiDestroyCalls).toBe(0)
        expect(MockDataTable.isDataTable(table)).toBe(true)
    })

    it('closes a Mercure EventSource created by a superseded connect', async () => {
        const table = mountTable({
            mercure: {
                hubUrl: '/.well-known/mercure',
                topics: ['/tables/1'],
            },
        })
        const controller = await getController(application, table)

        const staleSource: MockEventSource = { close: vi.fn() }
        vi.mocked(createMercureSubscription).mockImplementationOnce(() => {
            controller.disconnect()
            return staleSource as unknown as EventSource
        })

        loadResolvers[0](MockDataTable)

        await vi.waitFor(() => {
            expect(createMercureSubscription).toHaveBeenCalledTimes(1)
        })

        expect(staleSource.close).toHaveBeenCalledTimes(1)
        expect((controller as { eventSource: EventSource | null }).eventSource).toBeNull()
    })
})

function createMockDataTable(
    initialized: WeakSet<Element>,
    instances: MockInstance[],
    onApiDestroy: () => void
) {
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
            onApiDestroy()
            initialized.delete(this.element)
        }
    }

    return MockDataTable
}

function mountTable(extraView: Record<string, unknown> = {}): HTMLTableElement {
    const table = document.createElement('table')
    table.setAttribute('data-controller', 'datatable')
    table.setAttribute(
        'data-datatable-view-value',
        JSON.stringify({
            columns: [{ data: 'name', title: 'Name' }],
            ...extraView,
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
