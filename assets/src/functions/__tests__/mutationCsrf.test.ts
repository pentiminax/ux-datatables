import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { deleteEntity } from '../deleteEntity.js'
import { toggleBooleanValue } from '../toggleBooleanValue.js'

function headersOf(call: unknown): Record<string, string> {
    const init = (call as [string, RequestInit])[1]
    return init.headers as Record<string, string>
}

describe('mutation CSRF header', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200 } as Response))
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('deleteEntity sends the X-CSRF-Token header when a token is provided', async () => {
        await deleteEntity({ entity: 'App\\Entity\\User', id: '5', csrfToken: 'tok-123' })

        const headers = headersOf((fetch as any).mock.calls[0])
        expect(headers['X-CSRF-Token']).toBe('tok-123')
    })

    it('deleteEntity omits the X-CSRF-Token header when no token is provided', async () => {
        await deleteEntity({ entity: 'App\\Entity\\User', id: '5' })

        const headers = headersOf((fetch as any).mock.calls[0])
        expect(headers['X-CSRF-Token']).toBeUndefined()
    })

    it('toggleBooleanValue sends the X-CSRF-Token header when a token is provided', async () => {
        await toggleBooleanValue({
            url: '/datatables/ajax/edit',
            id: '5',
            entity: 'App\\Entity\\User',
            field: 'active',
            newValue: true,
            csrfToken: 'tok-456',
        })

        const headers = headersOf((fetch as any).mock.calls[0])
        expect(headers['X-CSRF-Token']).toBe('tok-456')
    })

    it('toggleBooleanValue omits the X-CSRF-Token header when no token is provided', async () => {
        await toggleBooleanValue({
            url: '/datatables/ajax/edit',
            id: '5',
            entity: 'App\\Entity\\User',
            field: 'active',
            newValue: true,
        })

        const headers = headersOf((fetch as any).mock.calls[0])
        expect(headers['X-CSRF-Token']).toBeUndefined()
    })
})
