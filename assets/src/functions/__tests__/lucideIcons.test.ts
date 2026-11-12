import { afterEach, describe, expect, it, vi } from 'vitest'
import { hasLucideIcons, hasLucideIconsInActions } from '../lucideIcons.js'

describe('hasLucideIcons', () => {
    it('detects an icon column', () => {
        expect(hasLucideIcons([{ customOptions: { isIcon: true } }])).toBe(true)
    })

    it('detects a Lucide action without an icon column', () => {
        expect(
            hasLucideIcons([
                {
                    actions: [{ type: 'EDIT', lucideIcon: 'pencil' }],
                },
            ])
        ).toBe(true)
    })

    it('does not load Lucide for CSS action icons', () => {
        expect(
            hasLucideIcons([
                {
                    actions: [{ type: 'EDIT', icon: 'bi bi-pencil' }],
                },
            ])
        ).toBe(false)
    })

    it('ignores malformed columns and blank Lucide icon names', () => {
        expect(hasLucideIcons([null, {}, { actions: 'invalid' }])).toBe(false)
        expect(hasLucideIcons([{ actions: [{ lucideIcon: '   ' }] }])).toBe(false)
    })

    it('detects a Lucide bulk action', () => {
        expect(hasLucideIconsInActions([{ name: 'approve', lucideIcon: 'check' }])).toBe(true)
        expect(hasLucideIconsInActions([{ name: 'approve', icon: 'bi bi-check' }])).toBe(false)
    })
})

describe('loadLucideIcons', () => {
    afterEach(() => {
        vi.restoreAllMocks()
        vi.resetModules()
    })

    it('warns and renders no icon when Lucide is not registered', async () => {
        vi.resetModules()
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        const { loadLucideIcons, renderLucideIcon } = await import('../lucideIcons.js')

        await expect(loadLucideIcons()).resolves.toBeUndefined()

        expect(warn).toHaveBeenCalledTimes(1)
        expect(warn.mock.calls[0][0]).toContain('The "lucide" package is not registered')
        expect(renderLucideIcon('pencil')).toBeNull()
    })
})
