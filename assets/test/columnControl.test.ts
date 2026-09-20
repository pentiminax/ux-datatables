import { describe, expect, it } from 'vitest'
import { normalizeDisabledColumnControls } from '../src/functions/columnControl'

describe('normalizeDisabledColumnControls', () => {
    it('disables every globally configured target', () => {
        const payload = {
            columnControl: [
                { target: 0, content: ['order'] },
                { target: 1, content: ['search'] },
            ],
            columns: [{ columnControl: [] }],
        }

        normalizeDisabledColumnControls(payload)

        expect(payload.columns[0].columnControl).toEqual([
            { target: 0, content: [] },
            { target: 1, content: [] },
        ])
    })

    it('uses the default target for a simple global configuration', () => {
        const payload = {
            columnControl: ['search'],
            columns: [{ columnControl: [] }],
        }

        normalizeDisabledColumnControls(payload)

        expect(payload.columns[0].columnControl).toEqual([{ target: 0, content: [] }])
    })

    it('deduplicates global targets', () => {
        const payload = {
            columnControl: [
                { target: 1, content: ['search'] },
                { target: 1, content: ['searchList'] },
                { target: 'tfoot', content: ['search'] },
            ],
            columns: [{ columnControl: [] }],
        }

        normalizeDisabledColumnControls(payload)

        expect(payload.columns[0].columnControl).toEqual([
            { target: 1, content: [] },
            { target: 'tfoot', content: [] },
        ])
    })

    it('leaves columns without an empty ColumnControl override unchanged', () => {
        const columns = [
            { name: 'enabled' },
            { name: 'custom', columnControl: ['searchList'] },
        ]
        const payload = {
            columnControl: [{ target: 1, content: ['search'] }],
            columns,
        }

        normalizeDisabledColumnControls(payload)

        expect(payload.columns).toEqual(columns)
    })

    it('preserves the empty override when no global configuration exists', () => {
        const payload = {
            columns: [{ columnControl: [] }],
        }

        normalizeDisabledColumnControls(payload)

        expect(payload.columns[0].columnControl).toEqual([])
    })
})
