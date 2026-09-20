import { describe, expect, it } from 'vitest'
import { emailColumnRenderer } from '../src/columnRenderers/emailColumnRenderer'

describe('emailColumnRenderer', () => {
  it('matches email columns', () => {
    expect(emailColumnRenderer.matches({ customOptions: { isEmail: true } })).toBe(true)
  })

  it('does not match plain columns', () => {
    expect(emailColumnRenderer.matches({ data: 'email' })).toBe(false)
  })

  describe('configure', () => {
    it('returns raw data for non-display types', () => {
      const column: Record<string, any> = { customOptions: { isEmail: true } }
      emailColumnRenderer.configure(column)
      expect(column.render('user@example.com', 'filter', {})).toBe('user@example.com')
    })

    it('renders a mailto link by default', () => {
      const column: Record<string, any> = { customOptions: { isEmail: true } }
      emailColumnRenderer.configure(column)
      const html = column.render('user@example.com', 'display', {})
      expect(html).toBe('<a href="mailto:user@example.com">user@example.com</a>')
    })

    it('renders plain text when renderAsText is enabled', () => {
      const column: Record<string, any> = {
        customOptions: { isEmail: true, renderAsText: true },
      }
      emailColumnRenderer.configure(column)
      const html = column.render('user@example.com', 'display', {})
      expect(html).toBe('user@example.com')
    })

    it('applies masking in plain text mode', () => {
      const column: Record<string, any> = {
        customOptions: { isEmail: true, renderAsText: true, mask: true },
      }
      emailColumnRenderer.configure(column)
      const html = column.render('user@example.com', 'display', {})
      expect(html).toBe('u***@example.com')
    })

    it('uses display value in plain text mode', () => {
      const column: Record<string, any> = {
        customOptions: {
          isEmail: true,
          renderAsText: true,
          displayValue: 'Contact us',
        },
      }
      emailColumnRenderer.configure(column)
      const html = column.render('user@example.com', 'display', {})
      expect(html).toBe('Contact us')
    })

    it('returns empty string for empty email', () => {
      const column: Record<string, any> = { customOptions: { isEmail: true } }
      emailColumnRenderer.configure(column)
      expect(column.render('', 'display', {})).toBe('')
    })
  })
})
