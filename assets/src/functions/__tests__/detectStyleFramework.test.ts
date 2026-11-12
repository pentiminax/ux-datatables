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

        expect(warn).toHaveBeenCalledTimes(1)
        expect(warn.mock.calls[0][0]).toContain('Bootstrap 3 and 4 styling is deprecated')
        expect(warn.mock.calls[0][0]).toContain('"bs5"')
        expect(warn.mock.calls[0][0]).toContain('"dt"')
    })

    it.each([
        ['bs5', '/css/dataTables.bootstrap5.min.css'],
        ['dt', '/css/dataTables.dataTables.min.css'],
    ])('stays quiet when autodetection resolves to %s', async (key, href) => {
        const { detectStyleFramework } = await import('../detectStyleFramework.js')
        loadStylesheet(href)

        expect(detectStyleFramework()).toBe(key)
        expect(warn).not.toHaveBeenCalled()
    })
})
