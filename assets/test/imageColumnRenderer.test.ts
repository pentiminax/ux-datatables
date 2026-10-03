import { describe, expect, it } from 'vitest'
import { imageColumnRenderer } from '../src/columnRenderers/imageColumnRenderer'

function render(customOptions: Record<string, unknown>): string {
    const column: Record<string, any> = { customOptions: { isImage: true, ...customOptions } }
    imageColumnRenderer.configure(column)

    return column.render('/a.png', 'display')
}

describe('imageColumnRenderer', () => {
    it('emits the Bootstrap and the Tailwind rounded class names when rounded', () => {
        expect(render({ rounded: true })).toContain('class="rounded-circle rounded-full"')
    })

    it('adds no class by default', () => {
        expect(render({})).not.toContain('class=')
    })
})
