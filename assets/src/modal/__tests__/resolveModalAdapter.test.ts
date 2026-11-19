import { describe, expect, it, vi } from 'vitest'
import { DialogModalAdapter } from '../DialogModalAdapter.js'
import { resolveModalAdapter } from '../resolveModalAdapter.js'

describe('resolveModalAdapter', () => {
    it('resolves the dialog adapter for the dt framework', async () => {
        const adapter = await resolveModalAdapter(null, 'dt')

        expect(adapter).toBeInstanceOf(DialogModalAdapter)
    })

    it('returns null for bs5 until the application registers its adapter', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(await resolveModalAdapter(null, 'bs5')).toBeNull()
        expect(error).toHaveBeenCalledWith(expect.stringContaining("modalAdapters.register('bs5'"))
    })

    it('prefers an explicit adapter key over the detected framework', async () => {
        const adapter = await resolveModalAdapter('dt', 'bs5')

        expect(adapter).toBeInstanceOf(DialogModalAdapter)
    })

    it('returns null when no adapter is registered', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        const adapter = await resolveModalAdapter('missing', 'dt')

        expect(adapter).toBeNull()
        expect(error).toHaveBeenCalledWith(
            expect.stringContaining('No modal adapter registered for "missing".')
        )
    })
})
