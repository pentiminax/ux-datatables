import { describe, expect, it, vi } from 'vitest'
import { BootstrapModalAdapter } from '../BootstrapModalAdapter.js'
import { DialogModalAdapter } from '../DialogModalAdapter.js'
import { resolveModalAdapter } from '../resolveModalAdapter.js'

vi.mock('bootstrap', () => ({
    Modal: class {
        constructor(private readonly element: HTMLElement) {}

        show(): void {}

        hide(): void {
            this.element.dispatchEvent(new Event('hidden.bs.modal'))
        }

        dispose(): void {}
    },
}))

describe('resolveModalAdapter', () => {
    it('resolves the dialog adapter for the dt framework', async () => {
        const adapter = await resolveModalAdapter(null, 'dt')

        expect(adapter).toBeInstanceOf(DialogModalAdapter)
    })

    it('resolves the bootstrap adapter for the bs5 framework', async () => {
        const adapter = await resolveModalAdapter(null, 'bs5')

        expect(adapter).toBeInstanceOf(BootstrapModalAdapter)
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
            '[ux-datatables] No modal adapter registered for "missing".'
        )
    })
})
