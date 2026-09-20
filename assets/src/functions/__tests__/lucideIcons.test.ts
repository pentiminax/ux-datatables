import { describe, expect, it } from 'vitest'
import { hasLucideIcons } from '../lucideIcons.js'

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
})
