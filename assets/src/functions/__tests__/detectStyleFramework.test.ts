import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function loadStylesheet(href: string): void {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = href
    document.head.appendChild(link)
    Object.defineProperty(document, 'styleSheets', {
        configurable: true,
        value: [...(document.styleSheets ?? []), { href: link.href }],
    })
}

describe('detectStyleFramework', () => {
    let warn: ReturnType<typeof vi.spyOn>

    beforeEach(() => {
        vi.resetModules()
        warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
        Object.defineProperty(document, 'styleSheets', { configurable: true, value: [] })
    })

    afterEach(() => {
        warn.mockRestore()
        document.head.innerHTML = ''
    })

    it('warns once per page that autodetection is removed in 2.0', async () => {
        const { detectStyleFramework } = await import('../detectStyleFramework.js')
        loadStylesheet('/css/dataTables.dataTables.min.css')

        detectStyleFramework()
        detectStyleFramework()

        const autodetection = warn.mock.calls.filter(([m]) => String(m).includes('autodetected'))
        expect(autodetection).toHaveLength(1)
        expect(autodetection[0][0]).toContain('style_framework')
        expect(autodetection[0][0]).toContain('styleFramework()')
    })

    it('warns about autodetection in registered mode too', async () => {
        const { packageLoaders } = await import('../packageLoaders.js')
        const { detectStyleFramework } = await import('../detectStyleFramework.js')
        packageLoaders.register('dataTables', async () => ({}))
        loadStylesheet('/css/dataTables.dataTables.min.css')

        detectStyleFramework()

        expect(warn.mock.calls.some(([m]) => String(m).includes('autodetected'))).toBe(true)
    })
})
