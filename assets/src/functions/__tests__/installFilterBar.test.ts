import { afterEach, describe, expect, it, vi } from 'vitest'
import { type FilterBar, installFilterBar } from '../filters.js'

function dataTableStub() {
    const reload = vi.fn()

    return {
        reload,
        feature: { register: vi.fn() },
        Api: class {
            ajax = { reload }
        },
    }
}

function filtersPayload(extra: Record<string, any> = {}): Record<string, any> {
    return { filters: [{ name: 'name', type: 'text' }], ...extra }
}

function install(payload: Record<string, any>, DataTable = dataTableStub()) {
    return installFilterBar(payload, DataTable, 'dt') as FilterBar
}

function featureCallback(DataTable: ReturnType<typeof dataTableStub>) {
    return DataTable.feature.register.mock.calls[0][1] as (settings: any, opts: any) => HTMLElement
}

describe('installFilterBar', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('registers the "filters" feature once per DataTable, even without filters', () => {
        const DataTable = dataTableStub()

        expect(installFilterBar({}, DataTable, 'dt')).toBeNull()
        installFilterBar(filtersPayload(), DataTable, 'dt')

        expect(DataTable.feature.register).toHaveBeenCalledTimes(1)
        expect(DataTable.feature.register).toHaveBeenCalledWith('filters', expect.any(Function))
    })

    it('leaves a payload without filters untouched', () => {
        const payload: Record<string, any> = { stateSave: true }

        installFilterBar(payload, dataTableStub(), 'dt')

        expect(payload).toEqual({ stateSave: true })
    })

    it('renders the instance and reloads the table Ajax for Ajax tables', () => {
        const DataTable = dataTableStub()
        install(filtersPayload(), DataTable)
        const node = document.createElement('div')
        const instance = { render: vi.fn().mockReturnValue(node) }

        expect(featureCallback(DataTable)({ ajax: '/api/data' }, { instance })).toBe(node)

        instance.render.mock.calls[0][0]()
        expect(DataTable.reload).toHaveBeenCalledWith(null, true)
    })

    it('refuses to render the filter bar without an Ajax source', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        const DataTable = dataTableStub()
        install(filtersPayload(), DataTable)
        const instance = { render: vi.fn() }

        const result = featureCallback(DataTable)({}, { instance })

        expect(result).toBeInstanceOf(HTMLDivElement)
        expect(result.childNodes).toHaveLength(0)
        expect(instance.render).not.toHaveBeenCalled()
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('serverSide()'))
    })

    it('renders an empty node for a layout marker without an instance', () => {
        const DataTable = dataTableStub()
        installFilterBar({}, DataTable, 'dt')

        expect(featureCallback(DataTable)({ ajax: '/api/data' }, undefined)).toBeInstanceOf(
            HTMLDivElement
        )
    })

    describe('layout', () => {
        it('appends the feature next to search when no layout is set', () => {
            const payload = filtersPayload()
            const instance = install(payload)

            expect(payload.layout).toEqual({ topEnd: ['search', { filters: { instance } }] })
        })

        it('appends to an existing topEnd array, preserving entries', () => {
            const payload = filtersPayload({ layout: { topEnd: ['search', 'pageLength'] } })
            const instance = install(payload)

            expect(payload.layout.topEnd).toEqual([
                'search',
                'pageLength',
                { filters: { instance } },
            ])
        })

        it('wraps a scalar topEnd into an array', () => {
            const payload = filtersPayload({ layout: { topEnd: 'search' } })
            const instance = install(payload)

            expect(payload.layout.topEnd).toEqual(['search', { filters: { instance } }])
        })

        it('does not touch other layout slots when appending', () => {
            const payload = filtersPayload({
                layout: { topStart: 'pageLength', bottomEnd: 'paging' },
            })
            const instance = install(payload)

            expect(payload.layout.topStart).toBe('pageLength')
            expect(payload.layout.bottomEnd).toBe('paging')
            expect(payload.layout.topEnd).toEqual(['search', { filters: { instance } }])
        })

        it('replaces a "filters" string marker placed via PHP, keeping its position', () => {
            const payload = filtersPayload({ layout: { topEnd: ['filters', 'search'] } })
            const instance = install(payload)

            expect(payload.layout.topEnd).toEqual([{ filters: { instance } }, 'search'])
        })

        it('replaces a standalone "filters" marker on a slot', () => {
            const payload = filtersPayload({ layout: { topStart: 'filters' } })
            const instance = install(payload)

            expect(payload.layout.topStart).toEqual({ filters: { instance } })
            expect(payload.layout.topEnd).toBeUndefined()
        })
    })

    describe('saved state', () => {
        it('adds no state hooks when stateSave is off', () => {
            const payload = filtersPayload()
            install(payload)

            expect(payload.stateSaveParams).toBeUndefined()
            expect(payload.stateLoaded).toBeUndefined()
        })

        it('restores the saved filters and saves them back under uxFilters', () => {
            const payload = filtersPayload({ stateSave: true })
            const filterBar = install(payload)

            payload.stateLoaded({}, { uxFilters: { name: 'john', unknown: 'x' } })
            expect(filterBar.collectValues()).toEqual({ name: 'john' })

            const saved: Record<string, any> = {}
            payload.stateSaveParams({}, saved)
            expect(saved.uxFilters).toEqual({ name: 'john' })
        })

        it('runs the user callbacks before its own', () => {
            const calls: string[] = []
            const userSave = vi.fn((_settings: any, data: any) => {
                calls.push(`save:${'uxFilters' in data}`)
            })
            const userLoaded = vi.fn(() => calls.push('loaded'))
            const payload = filtersPayload({
                stateSave: true,
                stateSaveParams: userSave,
                stateLoaded: userLoaded,
            })
            install(payload)
            const settings = { id: 'settings' }

            payload.stateLoaded(settings, { uxFilters: { name: 'ada' } })
            const saved: Record<string, any> = {}
            payload.stateSaveParams(settings, saved)

            expect(userLoaded).toHaveBeenCalledWith(settings, { uxFilters: { name: 'ada' } })
            expect(userSave).toHaveBeenCalledWith(settings, saved)
            expect(calls).toEqual(['loaded', 'save:false'])
            expect(saved.uxFilters).toEqual({ name: 'ada' })
        })

        it('ignores a saved state without filters', () => {
            const payload = filtersPayload({ stateSave: true })
            const filterBar = install(payload)

            payload.stateLoaded({}, null)
            payload.stateLoaded({}, {})

            expect(filterBar.collectValues()).toEqual({})
        })
    })
})
