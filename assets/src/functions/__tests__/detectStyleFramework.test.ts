import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { detectStyleFramework } from '../detectStyleFramework.js'

/**
 * jsdom does not populate document.styleSheets for external <link> elements without
 * `resources: 'usable'`, which would require real network fetches. detectStyleFramework()
 * only ever reads `.href` off each sheet, so a minimal fake stands in for the real CSSOM.
 */
function stubStyleSheets(sheets: Array<{ href: string | null }>): void {
    Object.defineProperty(document, 'styleSheets', {
        configurable: true,
        value: sheets,
    })
}

describe('detectStyleFramework', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        stubStyleSheets([])
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.restoreAllMocks()
    })

    it('resolves immediately when a matching stylesheet is already present', async () => {
        stubStyleSheets([{ href: '/build/datatables.net-bs5/css/dataTables.bootstrap5.min.css' }])

        const result = await detectStyleFramework()

        expect(result).toBe('bs5')
    })

    it('prefers the more specific bootstrap5 pattern over the plain bootstrap one', async () => {
        stubStyleSheets([
            { href: '/build/dataTables.bootstrap.min.css' },
            { href: '/build/dataTables.bootstrap5.min.css' },
        ])

        const result = await detectStyleFramework()

        expect(result).toBe('bs5')
    })

    it('ignores stylesheets without an href', async () => {
        stubStyleSheets([{ href: null }])
        vi.spyOn(console, 'warn').mockImplementation(() => {})

        const promise = detectStyleFramework()
        await vi.advanceTimersByTimeAsync(500)

        expect(await promise).toBe('dt')
    })

    it('resolves once a matching stylesheet lands after a short delay (the fetch: "lazy" race)', async () => {
        const promise = detectStyleFramework()

        // Simulates the stylesheet import landing in document.styleSheets a tick after
        // connect() started checking, exactly the gap fetch: "lazy" can introduce.
        await vi.advanceTimersByTimeAsync(40)
        stubStyleSheets([{ href: '/build/dataTables.foundation.min.css' }])
        await vi.advanceTimersByTimeAsync(40)

        expect(await promise).toBe('zf')
    })

    it('falls back to "dt" and warns once no stylesheet appears within the retry window', async () => {
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

        const promise = detectStyleFramework()
        await vi.advanceTimersByTimeAsync(500)

        const result = await promise

        expect(result).toBe('dt')
        expect(warnSpy).toHaveBeenCalledWith(
            'No DataTables stylesheet detected. Make sure a DataTables CSS file is loaded. Falling back to "dt".'
        )
    })
})
