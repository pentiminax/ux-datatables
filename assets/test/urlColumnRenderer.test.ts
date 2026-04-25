import { describe, expect, it } from 'vitest'
import { urlColumnRenderer } from '../src/columnRenderers/urlColumnRenderer'

describe('urlColumnRenderer', () => {
  it('matches URL columns', () => {
    expect(urlColumnRenderer.matches({ customOptions: { isUrl: true } })).toBe(true)
  })

  it('matches columns with target in customOptions', () => {
    expect(urlColumnRenderer.matches({ customOptions: { target: '_blank' } })).toBe(true)
  })

  it('matches columns with displayValue in customOptions', () => {
    expect(urlColumnRenderer.matches({ customOptions: { displayValue: 'View' } })).toBe(true)
  })

  it('matches columns with showExternalIcon in customOptions', () => {
    expect(urlColumnRenderer.matches({ customOptions: { showExternalIcon: true } })).toBe(true)
  })

  it('does not match plain columns', () => {
    expect(urlColumnRenderer.matches({ data: 'name' })).toBe(false)
  })

  describe('configure', () => {
    it('returns raw data for non-display types', () => {
      const column: Record<string, any> = { customOptions: { target: '_blank' } }
      urlColumnRenderer.configure(column)
      expect(column.render('https://example.com', 'filter', {})).toBe('https://example.com')
    })

    it('renders an anchor tag from raw data URL', () => {
      const column: Record<string, any> = { customOptions: {} }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toBe('<a href="https://example.com">https://example.com</a>')
    })

    it('uses resolved row URL metadata as href', () => {
      const column: Record<string, any> = {
        customOptions: { isUrl: true },
        data: 'profile',
      }
      urlColumnRenderer.configure(column)
      const html = column.render('Jane', 'display', {
        __ux_datatables_urls: { profile: '/users/7' },
      })
      expect(html).toBe('<a href="/users/7">Jane</a>')
    })

    it('renders a custom display value', () => {
      const column: Record<string, any> = { customOptions: { displayValue: 'View Profile' } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('View Profile')
    })

    it('adds target attribute when target is set', () => {
      const column: Record<string, any> = { customOptions: { target: '_blank' } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('target="_blank"')
    })

    it('adds rel="noopener noreferrer" for target _blank', () => {
      const column: Record<string, any> = { customOptions: { target: '_blank' } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('rel="noopener noreferrer"')
    })

    it('does not add rel for other targets', () => {
      const column: Record<string, any> = { customOptions: { target: '_self' } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).not.toContain('rel=')
    })

    it('appends the external icon when showExternalIcon is true', () => {
      const column: Record<string, any> = { customOptions: { showExternalIcon: true } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('<span aria-label="external link">&#x2197;</span>')
    })

    it('escapes unsafe javascript: URLs and returns plain text', () => {
      const column: Record<string, any> = { customOptions: {} }
      urlColumnRenderer.configure(column)
      const result = column.render('javascript:alert(1)', 'display', {})
      expect(result).not.toContain('<a')
      expect(result).toBe('javascript:alert(1)')
    })

    it('escapes unsafe data: URLs and returns plain text', () => {
      const column: Record<string, any> = { customOptions: {} }
      urlColumnRenderer.configure(column)
      const result = column.render('data:text/html,<h1>x</h1>', 'display', {})
      expect(result).not.toContain('<a')
    })

    it('escapes HTML in the display value', () => {
      const column: Record<string, any> = { customOptions: { displayValue: '<b>click</b>' } }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('&lt;b&gt;click&lt;/b&gt;')
      expect(html).not.toContain('<b>')
    })
  })
})
