import { beforeAll, describe, expect, it } from 'vitest'
import {
    createIconColumnRenderer,
    loadLucideIcons,
} from '../src/columnRenderers/iconColumnRenderer'
import { TailwindThemeColumnStyleAdapter } from '../src/columnStyles/TailwindThemeColumnStyleAdapter'

function render(customOptions: Record<string, unknown>, value: unknown): string {
    const column: Record<string, any> = {
        data: 'enabled',
        customOptions: { isIcon: true, boolean: true, ...customOptions },
    }
    createIconColumnRenderer(new TailwindThemeColumnStyleAdapter()).configure(column)

    return column.render(value, 'display', {})
}

describe('iconColumnRenderer boolean mode', () => {
    beforeAll(loadLucideIcons)

    it('falls back to a check and a cross when no icon is configured', () => {
        expect(render({}, true)).toContain('<svg')
        expect(render({}, true)).toContain('dt-icon--success')
        expect(render({}, true)).toBe(
            render({ trueIcon: 'circle-check', trueColor: 'success' }, true)
        )
        expect(render({}, false)).toContain('dt-icon--danger')
        expect(render({}, false)).toBe(
            render({ falseIcon: 'circle-x', falseColor: 'danger' }, false)
        )
    })

    it('keeps the configured icons and colors', () => {
        const options = { trueIcon: 'star', trueColor: 'warning', falseIcon: 'moon' }

        expect(render(options, true)).toContain('dt-icon--warning')
        expect(render(options, true)).not.toBe(render({}, true))
        expect(render(options, false)).not.toBe(render({}, false))
    })
})
