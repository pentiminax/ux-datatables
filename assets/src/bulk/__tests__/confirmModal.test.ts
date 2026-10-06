import { afterEach, describe, expect, it, vi } from 'vitest'
import { resolveModalAdapter } from '../../modal/resolveModalAdapter.js'
import { confirmAction, confirmBulkAction } from '../confirmModal.js'

vi.mock('../../modal/resolveModalAdapter.js', () => ({
    resolveModalAdapter: vi.fn(),
}))

const request = {
    message: 'Delete <b>this</b> row?',
    confirmLabel: 'Yes',
    cancelLabel: 'No',
    framework: 'dt' as const,
}

describe('confirmAction', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('falls back to the native confirm when no modal adapter resolves', async () => {
        vi.mocked(resolveModalAdapter).mockResolvedValue(null)
        const nativeConfirm = vi.spyOn(window, 'confirm').mockReturnValue(true)

        await expect(confirmAction(request)).resolves.toBe(true)
        expect(nativeConfirm).toHaveBeenCalledWith(request.message)
    })

    it('resolves true and hides the modal on submit, with the message escaped', async () => {
        const modal = {
            show: vi.fn(async (_html: string, handlers: { onSubmit: () => Promise<void> }) => {
                await handlers.onSubmit()
            }),
            hide: vi.fn(async () => {}),
            replaceBody: vi.fn(),
        }
        vi.mocked(resolveModalAdapter).mockResolvedValue(modal as never)

        await expect(confirmAction({ ...request, adapterKey: 'custom' })).resolves.toBe(true)

        expect(resolveModalAdapter).toHaveBeenCalledWith('custom', 'dt')
        expect(modal.show.mock.calls[0][0]).toContain('Delete &lt;b&gt;this&lt;/b&gt; row?')
        expect(modal.hide).toHaveBeenCalledTimes(1)
    })

    it('resolves false when the modal is canceled', async () => {
        const modal = {
            show: vi.fn(async (_html: string, handlers: { onCancel: () => void }) => {
                handlers.onCancel()
            }),
            hide: vi.fn(async () => {}),
            replaceBody: vi.fn(),
        }
        vi.mocked(resolveModalAdapter).mockResolvedValue(modal as never)

        await expect(confirmAction(request)).resolves.toBe(false)
        expect(modal.hide).not.toHaveBeenCalled()
    })

    it('keeps the deprecated bulk alias', () => {
        expect(confirmBulkAction).toBe(confirmAction)
    })
})
