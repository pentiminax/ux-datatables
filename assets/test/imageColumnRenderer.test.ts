import { describe, expect, it } from 'vitest'
import { bindImagePlaceholder, imageColumnRenderer } from '../src/columnRenderers/imageColumnRenderer'

function render(customOptions: Record<string, unknown>, data = '/a.png'): string {
    const column: Record<string, any> = { customOptions: { isImage: true, ...customOptions } }
    imageColumnRenderer.configure(column)

    return column.render(data, 'display')
}

describe('imageColumnRenderer', () => {
    it('emits the Bootstrap and the Tailwind rounded class names when rounded', () => {
        expect(render({ rounded: true })).toContain('class="rounded-circle rounded-full"')
    })

    it('adds no class by default', () => {
        expect(render({})).not.toContain('class=')
    })

    describe('placeholder fallback', () => {
        const malicious = "x');alert(1);//"

        function mount(placeholder: string): HTMLImageElement {
            const host = document.createElement('div')
            host.innerHTML = render({ placeholder }, '/broken.png')
            bindImagePlaceholder(host)

            return host.querySelector('img') as HTMLImageElement
        }

        it('renders no inline event handler, even with a malicious placeholder', () => {
            const img = mount(malicious)

            expect(img.hasAttribute('onerror')).toBe(false)
            expect(img.getAttribute('data-placeholder')).toBe(malicious)
        })

        it('swaps in the placeholder once when the image fails to load', () => {
            const img = mount('/p.png')

            img.dispatchEvent(new Event('error'))
            expect(img.getAttribute('src')).toBe('/p.png')

            img.setAttribute('src', '/again-broken.png')
            img.dispatchEvent(new Event('error'))
            expect(img.getAttribute('src')).toBe('/again-broken.png')
        })

        it('adds no placeholder attribute without a placeholder or when data is empty', () => {
            expect(render({})).not.toContain('data-placeholder')
            expect(render({ placeholder: '/p.png' }, '')).not.toContain('data-placeholder')
        })
    })
})
