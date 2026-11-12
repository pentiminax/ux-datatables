import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('registered package loading', () => {
    beforeEach(() => {
        // test/setup.ts registers the built-in loaders in the shared module graph.
        vi.resetModules()
    })

    it('rejects an unregistered package after importing only the core entry', async () => {
        const { packageLoaders } = await import('../core.js')

        await expect(packageLoaders.load('select', 'bs5')).rejects.toThrow(
            'The "select" package is not registered.'
        )
    })

    it('is undone by the main entry, which still brings the built-in loaders back in 1.x', async () => {
        const { packageLoaders } = await import('../index.js')

        expect(packageLoaders.has('select', 'bs5')).toBe(true)
    })
})
