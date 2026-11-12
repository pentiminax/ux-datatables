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

    it.each([
        ['bs', '/css/dataTables.bootstrap.min.css'],
        ['bs4', '/css/dataTables.bootstrap4.min.css'],
    ])('warns once per page when autodetection resolves to %s', async (key, href) => {
        const { detectStyleFramework } = await import('../detectStyleFramework.js')
        loadStylesheet(href)

        expect(detectStyleFramework()).toBe(key)
        expect(detectStyleFramework()).toBe(key)

        const deprecation = warn.mock.calls.filter(([m]) => String(m).includes('Bootstrap 3 and 4'))
        expect(deprecation).toHaveLength(1)
        expect(deprecation[0][0]).toContain('"bs5"')
        expect(deprecation[0][0]).toContain('"dt"')
    })

    it.each([
        ['bs5', '/css/dataTables.bootstrap5.min.css'],
        ['dt', '/css/dataTables.dataTables.min.css'],
    ])(
        'does not warn about Bootstrap 3 and 4 when autodetection resolves to %s',
        async (key, href) => {
            const { detectStyleFramework } = await import('../detectStyleFramework.js')
            loadStylesheet(href)

            expect(detectStyleFramework()).toBe(key)
            expect(warn.mock.calls.some(([m]) => String(m).includes('Bootstrap 3 and 4'))).toBe(
                false
            )
        }
    )

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
