import { describe, expect, it } from 'vitest'
import { urlColumnRenderer } from '../src/columnRenderers/urlColumnRenderer'

describe('urlColumnRenderer', () => {
  it('matches columns with urlTemplate', () => {
    expect(urlColumnRenderer.matches({ urlTemplate: '/users/{id}' })).toBe(true)
  })

  it('matches columns with urlTarget', () => {
    expect(urlColumnRenderer.matches({ urlTarget: '_blank' })).toBe(true)
  })

  it('matches columns with urlDisplayValue', () => {
    expect(urlColumnRenderer.matches({ urlDisplayValue: 'View' })).toBe(true)
  })

  it('matches columns with urlShowExternalIcon', () => {
    expect(urlColumnRenderer.matches({ urlShowExternalIcon: true })).toBe(true)
  })

  it('does not match plain columns', () => {
    expect(urlColumnRenderer.matches({ data: 'name' })).toBe(false)
  })

  describe('configure', () => {
    it('returns raw data for non-display types', () => {
      const column: Record<string, any> = { urlTarget: '_blank' }
      urlColumnRenderer.configure(column)
      expect(column.render('https://example.com', 'filter', {})).toBe('https://example.com')
    })

    it('renders an anchor tag from raw data URL', () => {
      const column: Record<string, any> = {}
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toBe('<a href="https://example.com">https://example.com</a>')
    })

    it('builds URL from urlTemplate and urlRouteParams', () => {
      const column: Record<string, any> = {
        urlTemplate: '/users/{id}/posts/{slug}',
        urlRouteParams: { id: 'userId', slug: 'postSlug' },
      }
      urlColumnRenderer.configure(column)
      const html = column.render('ignored', 'display', { userId: 7, postSlug: 'hello world' })
      expect(html).toContain('/users/7/posts/hello%20world')
    })

    it('renders a custom display value', () => {
      const column: Record<string, any> = { urlDisplayValue: 'View Profile' }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('View Profile')
    })

    it('adds target attribute when urlTarget is set', () => {
      const column: Record<string, any> = { urlTarget: '_blank' }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('target="_blank"')
    })

    it('adds rel="noopener noreferrer" for target _blank', () => {
      const column: Record<string, any> = { urlTarget: '_blank' }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('rel="noopener noreferrer"')
    })

    it('does not add rel for other targets', () => {
      const column: Record<string, any> = { urlTarget: '_self' }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).not.toContain('rel=')
    })

    it('appends the external icon when urlShowExternalIcon is true', () => {
      const column: Record<string, any> = { urlShowExternalIcon: true }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('<span aria-label="external link">&#x2197;</span>')
    })

    it('escapes unsafe javascript: URLs and returns plain text', () => {
      const column: Record<string, any> = {}
      urlColumnRenderer.configure(column)
      const result = column.render('javascript:alert(1)', 'display', {})
      expect(result).not.toContain('<a')
      expect(result).toBe('javascript:alert(1)')
    })

    it('escapes unsafe data: URLs and returns plain text', () => {
      const column: Record<string, any> = {}
      urlColumnRenderer.configure(column)
      const result = column.render('data:text/html,<h1>x</h1>', 'display', {})
      expect(result).not.toContain('<a')
    })

    it('escapes HTML in the display value', () => {
      const column: Record<string, any> = { urlDisplayValue: '<b>click</b>' }
      urlColumnRenderer.configure(column)
      const html = column.render('https://example.com', 'display', {})
      expect(html).toContain('&lt;b&gt;click&lt;/b&gt;')
      expect(html).not.toContain('<b>')
    })
  })
})
