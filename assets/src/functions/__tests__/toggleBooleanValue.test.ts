import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createBooleanColumnRenderer } from '../../columnRenderers/booleanColumnRenderer.js'
import type { ColumnStyleAdapter } from '../../columnStyles/ColumnStyleAdapter.js'
import { toggleBooleanValue } from '../toggleBooleanValue.js'

describe('toggleBooleanValue row id', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200 } as Response))
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('sends the table row id next to the target id', async () => {
        await toggleBooleanValue({
            url: '/datatables/ajax/edit',
            id: '7',
            rowId: '12',
            field: 'active',
            newValue: true,
            dataTable: 'signed-token',
        })

        const body = JSON.parse((fetch as any).mock.calls[0][1].body)
        expect(body).toMatchObject({ id: '7', rowId: '12' })
    })

    it('stamps the table row id on the rendered switch', () => {
        const renderSwitch = vi.fn(() => 'switch')
        const style = { renderSwitch } as unknown as ColumnStyleAdapter
        const column: Record<string, any> = {
            name: 'active',
            customOptions: { renderAsSwitch: true, toggleIdField: 'customer.id' },
        }

        createBooleanColumnRenderer('/toggle', true, style).configure(column)
        column.render(true, 'display', {
            customer: { id: 7 },
            __ux_datatables_boolean_switches: { active: '7' },
            __ux_datatables_switch_row_id: '12',
        })

        expect(renderSwitch).toHaveBeenCalledWith(
            expect.objectContaining({ dataId: '7', dataRowId: '12' })
        )
    })
})
