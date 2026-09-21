import { beforeEach, describe, expect, it } from 'vitest'
import { SelectionStore } from '../selectionStore.js'
import { FakeApi } from './fakeApi.js'

describe('SelectionStore', () => {
    let api: FakeApi
    let store: SelectionStore

    beforeEach(() => {
        api = new FakeApi(
            [
                { id: '1', selected: false },
                { id: '2', selected: false },
            ],
            10
        )
        store = new SelectionStore(api)
        store.attach(() => {})
    })

    it('counts the rows the user checked', () => {
        api.emitSelection('select', [0, 1])

        expect(store.snapshot()).toMatchObject({ ids: ['1', '2'], count: 2, allMatching: false })
    })

    it('keeps a selection the redraw of a server-side page destroyed', () => {
        api.emitSelection('select', [0])

        api.drawPage([
            { id: '3', selected: false },
            { id: '4', selected: false },
        ])
        api.drawPage([
            { id: '1', selected: false },
            { id: '2', selected: false },
        ])

        expect(store.snapshot().ids).toEqual(['1'])
        expect(api.rows({ selected: true }).ids().toArray()).toEqual(['1'])
    })

    it('counts every matching row once the user selects them all', () => {
        store.selectAllMatching()

        expect(store.snapshot()).toMatchObject({ allMatching: true, count: 10 })
        expect(api.rows({ selected: true }).ids().toArray()).toEqual(['1', '2'])
    })

    it('sends client-side matches as explicit identifiers', () => {
        api = new FakeApi(
            [
                { id: '00123', selected: false },
                { id: '9007199254740993', selected: false },
            ],
            2,
            false
        )
        store = new SelectionStore(api)
        store.attach(() => {})

        store.selectAllMatching()

        expect(store.snapshot()).toMatchObject({
            allMatching: false,
            ids: ['00123', '9007199254740993'],
            count: 2,
        })
        expect(api.rows({ selected: true }).ids().toArray()).toEqual([
            '00123',
            '9007199254740993',
        ])
    })

    it('tracks the rows unchecked after a select all', () => {
        store.selectAllMatching()
        api.emitSelection('deselect', [1])

        expect(store.snapshot()).toMatchObject({
            allMatching: true,
            deselectedIds: ['2'],
            count: 9,
        })
    })

    it('does not re-select a row deselected under select all when the page is redrawn', () => {
        store.selectAllMatching()
        api.emitSelection('deselect', [1])

        api.drawPage([
            { id: '1', selected: false },
            { id: '2', selected: false },
        ])

        expect(api.rows({ selected: true }).ids().toArray()).toEqual(['1'])
        expect(store.snapshot().deselectedIds).toEqual(['2'])
    })

    it('clears the selection and the table rows together', () => {
        api.emitSelection('select', [0, 1])
        store.clear()

        expect(store.snapshot()).toMatchObject({ ids: [], count: 0, allMatching: false })
        expect(api.rows({ selected: true }).ids().toArray()).toEqual([])
    })
})
