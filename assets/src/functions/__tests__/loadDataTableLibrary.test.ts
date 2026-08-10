import { afterEach, describe, expect, it, vi } from 'vitest'
import type { StyleFramework } from '../../types/styleFramework.js'

const frameworks: StyleFramework[] = ['dt', 'bs', 'bs4', 'bs5', 'zf', 'jqui', 'se']

describe('loadDataTableLibrary', () => {
    afterEach(() => {
        vi.resetModules()

        for (const framework of frameworks) {
            vi.doUnmock(`datatables.net-${framework}`)
        }
    })

    it.each(
        frameworks
    )('loads the datatables.net-%s package for the selected framework', async (framework) => {
        const DataTable = class {}

        vi.doMock(`datatables.net-${framework}`, () => ({ default: DataTable }))

        const { loadDataTableLibrary } = await import('../loadDataTableLibrary.js')

        await expect(loadDataTableLibrary(framework)).resolves.toBe(DataTable)
    })
})
