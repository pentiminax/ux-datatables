import { describe, expect, it, vi } from 'vitest'
import { BulkActionBar, installBulkActionBar } from '../BulkActionBar.js'

function dataTableStub() {
    return {
        feature: { register: vi.fn() },
        Api: class {
            constructor(public settings: unknown) {}
        },
    }
}

function bulkPayload(extra: Record<string, any> = {}): Record<string, any> {
    const { bulkActions, ...rest } = extra

    return {
        ...rest,
        bulkActions: { actions: [{ name: 'approve', label: 'Approve' }], ...bulkActions },
    }
}

function install(payload: Record<string, any>, DataTable = dataTableStub()) {
    return installBulkActionBar(payload, DataTable, 'dt', vi.fn(), () => ({}))
}

function featureCallback(DataTable: ReturnType<typeof dataTableStub>) {
    return DataTable.feature.register.mock.calls[0][1] as (settings: any, opts: any) => HTMLElement
}

describe('installBulkActionBar', () => {
    it('registers the "bulkActions" feature once per DataTable, even without bulk actions', () => {
        const DataTable = dataTableStub()

        expect(install({}, DataTable)).toBeNull()
        install(bulkPayload(), DataTable)

        expect(DataTable.feature.register).toHaveBeenCalledTimes(1)
        expect(DataTable.feature.register).toHaveBeenCalledWith('bulkActions', expect.any(Function))
    })

    it('leaves a payload without bulk actions untouched', () => {
        const payload: Record<string, any> = { bulkActions: { actions: [] } }

        install(payload)

        expect(payload).toEqual({ bulkActions: { actions: [] } })
    })

    it('renders the instance against the table API', () => {
        const DataTable = dataTableStub()
        install({}, DataTable)
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }
        const settings = { id: 'settings' }

        expect(featureCallback(DataTable)(settings, { instance })).toBe(node)
        expect(instance.render.mock.calls[0][0]).toEqual({ settings })
    })

    it('renders an empty node for a layout marker the user may not act on', () => {
        const DataTable = dataTableStub()
        install({}, DataTable)

        expect(featureCallback(DataTable)({}, undefined)).toBeInstanceOf(HTMLDivElement)
    })

    it('appends the bar to topEnd by default', () => {
        const payload = bulkPayload({ layout: { topEnd: 'search' } })
        const instance = install(payload)

        expect(instance).toBeInstanceOf(BulkActionBar)
        expect(payload.layout.topEnd).toEqual(['search', { bulkActions: { instance } }])
    })

    it('appends the bar to the configured position', () => {
        const payload = bulkPayload({ bulkActions: { position: 'bottomStart' } })
        const instance = install(payload)

        expect(payload.layout).toEqual({ bottomStart: [{ bulkActions: { instance } }] })
    })

    it('replaces a "bulkActions" marker placed via PHP', () => {
        const payload = bulkPayload({ layout: { topStart: ['pageLength', 'bulkActions'] } })
        const instance = install(payload)

        expect(payload.layout).toEqual({
            topStart: ['pageLength', { bulkActions: { instance } }],
        })
    })
})
