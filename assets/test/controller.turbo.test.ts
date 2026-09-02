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

vi.mock('../src/functions/deleteEntity.js', () => ({
    deleteEntity: vi.fn(async () => new Response(null, { status: 200 })),
}))

vi.mock('../src/functions/toggleBooleanValue.js', () => ({
    toggleBooleanValue: vi.fn(async () => new Response(null, { status: 200 })),
}))

import { deleteEntity } from '../src/functions/deleteEntity.js'
import { toggleBooleanValue } from '../src/functions/toggleBooleanValue.js'
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

    it('keeps the table when Stimulus reports a reparent instead of a detach', async () => {
        const table = mountTable()
        const controller = await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        controller.disconnect()

        expect(instances[0].destroy).not.toHaveBeenCalled()
    })

    it('does not duplicate mutation handlers after a reconnect', async () => {
        const table = mountTable({
            dataTable: 'mutation-token',
            mutationsEnabled: true,
        })
        await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        await detach(table)
        await attach(table)

        // The reconnect must not re-initialize: the table is still a live DataTable, so
        // connect() early-returns without loading the library or binding a second handler.
        expect(loadResolvers).toHaveLength(1)

        const deleteButton = document.createElement('button')
        deleteButton.setAttribute('data-action-type', 'DELETE')
        deleteButton.setAttribute('data-id', '42')
        table.appendChild(deleteButton)
        deleteButton.click()
        await flushMicrotasks()

        expect(deleteEntity).toHaveBeenCalledTimes(1)

        const toggle = document.createElement('input')
        toggle.type = 'checkbox'
        toggle.className = 'boolean-switch-action'
        toggle.dataset.id = '42'
        toggle.dataset.field = 'active'
        toggle.checked = true
        table.appendChild(toggle)
        toggle.dispatchEvent(new Event('change', { bubbles: true }))
        await flushMicrotasks()

        expect(toggleBooleanValue).toHaveBeenCalledTimes(1)
    })

    it('destroys the table when Turbo caches the page', async () => {
        const table = mountTable()
        await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        document.dispatchEvent(new Event('turbo:before-cache'))

        expect(instances[0].destroy).toHaveBeenCalledTimes(1)
    })

    it('still cleans the snapshot after a reparent-induced reconnect', async () => {
        const table = mountTable()
        await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        // A reparent is what DataTables' own DOM work does; Stimulus reports it as
        // disconnect + reconnect, which must not leave the listener unregistered.
        const host = document.createElement('div')
        document.body.appendChild(host)
        host.appendChild(table)
        await flushMicrotasks()

        document.dispatchEvent(new Event('turbo:before-cache'))

        expect(instances[0].destroy).toHaveBeenCalledTimes(1)
    })

    it('stops listening once the table is really gone', async () => {
        const table = mountTable()
        await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        await detach(table)

        document.dispatchEvent(new Event('turbo:before-cache'))

        expect(instances[0].destroy).not.toHaveBeenCalled()
    })

    it('leaves the table untouched when Turbo never caches the page', async () => {
        const table = mountTable()
        await getController(application, table)

        loadResolvers[0](MockDataTable)
        await flushMicrotasks()

        expect(instances[0].destroy).not.toHaveBeenCalled()
        expect(MockDataTable.isDataTable(table)).toBe(true)
    })
})

describe('datatable controller Turbo snapshot cleanup', () => {
    let application: Application
    let initCount: number

    beforeEach(() => {
        initCount = 0
        const initialized = new WeakSet<Element>()
        const built = new WeakMap<Element, MockInstance>()

        function MockDataTable(this: void, element: Element, _payload: unknown) {
            initCount++
            initialized.add(element)

            const container = document.createElement('div')
            container.className = 'dt-container'
            element.parentNode?.insertBefore(container, element)
            container.appendChild(element)

            const instance: MockInstance = {
                destroy: vi.fn(() => {
                    initialized.delete(element)
                    container.parentNode?.insertBefore(element, container)
                    container.remove()
                }),
                on: vi.fn(),
            }
            built.set(element, instance)

            return instance
        }

        MockDataTable.isDataTable = (element: Element) => initialized.has(element)
        // DataTables resolves `new DataTable.Api(node)` to the live instance for that table.
        MockDataTable.Api = class {
            constructor(element: Element) {
                return built.get(element) as object
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

    it('restores a bare table so the cached snapshot stays clean', async () => {
        const table = mountTable()
        await settle()

        expect(document.querySelectorAll('.dt-container')).toHaveLength(1)

        document.dispatchEvent(new Event('turbo:before-cache'))

        expect(document.querySelector('.dt-container')).toBeNull()
        expect(table.parentElement).toBe(document.body)
    })

    it('does not rebuild the table before Turbo clones the snapshot', async () => {
        mountTable()
        await settle()

        expect(initCount).toBe(1)

        document.dispatchEvent(new Event('turbo:before-cache'))

        // Turbo clones the DOM one event-loop tick after the event, so anything the
        // reconnect rebuilds here would end up inside the snapshot.
        await settle()

        expect(initCount).toBe(1)
        expect(document.querySelector('.dt-container')).toBeNull()
    })
})

function createMockDataTable(initialized: WeakSet<Element>, instances: MockInstance[]) {
    const built = new WeakMap<Element, MockInstance>()

    function MockDataTable(this: void, element: Element, _payload: unknown) {
        initialized.add(element)
        const instance: MockInstance = {
            destroy: vi.fn(() => {
                initialized.delete(element)
            }),
            on: vi.fn(),
        }
        instances.push(instance)
        built.set(element, instance)
        return instance
    }

    MockDataTable.isDataTable = (element: Element) => initialized.has(element)
    // DataTables resolves `new DataTable.Api(node)` to the live instance for that table.
    MockDataTable.Api = class {
        constructor(element: Element) {
            return built.get(element) as object
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

async function detach(table: HTMLTableElement): Promise<void> {
    table.remove()
    await flushMicrotasks()
}

async function attach(table: HTMLTableElement): Promise<void> {
    document.body.appendChild(table)
    await flushMicrotasks()
}

async function flushMicrotasks(): Promise<void> {
    await Promise.resolve()
    await Promise.resolve()
    await Promise.resolve()
}

async function settle(): Promise<void> {
    for (let i = 0; i < 20; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}
