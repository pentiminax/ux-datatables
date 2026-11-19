import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('registered package loading', () => {
    beforeEach(() => {
        // test/setup.ts registers loaders in the shared module graph.
        vi.resetModules()
    })

    it('rejects an unregistered package after importing only the core entry', async () => {
        const { packageLoaders } = await import('../core.js')

        await expect(packageLoaders.load('select', 'bs5')).rejects.toThrow(
            'The "select" package is not registered.'
        )
    })

    it('exports the controller as the default and the registries from the main entry', async () => {
        const entry = await import('../core.js')

        expect(entry.default).toBe(entry.DataTableController)
        expect(entry.packageLoaders).toBeDefined()
        expect(entry.modalAdapters.get('dt')).not.toBeNull()
    })
})
