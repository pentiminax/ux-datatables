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

const INSTANCE_LIMIT = 5

describe('datatable controller DOM wrapping', () => {
    let application: Application
    let initCount: number

    beforeEach(() => {
        initCount = 0
        const initialized = new WeakSet<Element>()

        function MockDataTable(this: void, element: Element, _payload: unknown) {
            initCount++
            initialized.add(element)

            let container: HTMLElement | null = null

            // DataTables 3 wraps the table in div.dt-container, which reparents the element.
            if (initCount <= INSTANCE_LIMIT) {
                container = document.createElement('div')
                container.className = 'dt-container'
                element.parentNode?.insertBefore(container, element)
                container.appendChild(element)
            }

            return {
                destroy: vi.fn(() => {
                    initialized.delete(element)

                    if (container) {
                        container.parentNode?.insertBefore(element, container)
                        container.remove()
                    }
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

        application = Application.start()
        application.register('datatable', DatatableController)
    })

    afterEach(() => {
        application.stop()
        document.body.innerHTML = ''
        vi.clearAllMocks()
    })

    it('initializes once when DataTables reparents the table element', async () => {
        const table = document.createElement('table')
        table.setAttribute('data-controller', 'datatable')
        table.setAttribute(
            'data-datatable-view-value',
            JSON.stringify({ columns: [{ data: 'name', title: 'Name' }] })
        )
        document.body.appendChild(table)

        await settle()

        expect(initCount).toBe(1)
        expect(table.parentElement?.className).toBe('dt-container')
        expect(document.querySelectorAll('.dt-container')).toHaveLength(1)
    })
})

async function settle(): Promise<void> {
    for (let i = 0; i < 20; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0))
    }
}
