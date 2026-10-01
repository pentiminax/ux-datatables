import { describe, expect, it, vi } from 'vitest'
import { registerBulkActionsFeature as registerBulk } from '../../bulk/BulkActionBar.js'
import { registerBulkActionsFeature } from '../bulkActionsFeature.js'
import { applyBulkActionsLayout } from '../bulkActionsLayout.js'
import { registerFilterFeature } from '../filterFeature.js'
import { applyFilterLayout } from '../filterLayout.js'
import { registerFilterFeature as registerFilters } from '../filters.js'

describe('deprecated feature paths', () => {
    it('re-export the registrations the install entry points use', () => {
        expect(registerFilterFeature).toBe(registerFilters)
        expect(registerBulkActionsFeature).toBe(registerBulk)

        const DataTable = { feature: { register: vi.fn() } }
        registerFilterFeature(DataTable)
        registerFilterFeature(DataTable)

        expect(DataTable.feature.register).toHaveBeenCalledTimes(1)
    })

    it('still place the bars in the layout', () => {
        const filterBar = {} as any
        const bulkBar = {} as any
        const payload: Record<string, any> = {}

        applyFilterLayout(payload, filterBar)
        applyBulkActionsLayout(payload, bulkBar, 'topStart')

        expect(JSON.stringify(payload.layout)).toContain('filters')
        expect(payload.layout.topStart).toEqual([{ bulkActions: { instance: bulkBar } }])
    })
})
