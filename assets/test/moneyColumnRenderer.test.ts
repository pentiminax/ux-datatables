import { describe, expect, it } from 'vitest'
import { moneyColumnRenderer } from '../src/columnRenderers/moneyColumnRenderer'

describe('moneyColumnRenderer', () => {
  it('matches money columns', () => {
    expect(moneyColumnRenderer.matches({ customOptions: { isMoney: true } })).toBe(true)
  })

  it('does not match plain columns', () => {
    expect(moneyColumnRenderer.matches({ customOptions: { isMoney: false } })).toBe(false)
    expect(moneyColumnRenderer.matches({ data: 'price' })).toBe(false)
  })

  describe('configure', () => {
    it('formats cent values as currency for display mode', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          currency: 'USD',
          decimals: 2,
          locale: 'en-US',
          storedAsCents: true,
        },
      }

      moneyColumnRenderer.configure(column)

      expect(column.render(12345, 'display')).toBe('$123.45')
    })

    it('formats without currency sign when showCurrencySign is false', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          currency: 'USD',
          decimals: 2,
          locale: 'en-US',
          storedAsCents: true,
          showCurrencySign: false,
        },
      }

      moneyColumnRenderer.configure(column)

      expect(column.render(12345, 'display')).toBe('123.45')
    })

    it('shows currency sign by default', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          currency: 'USD',
          decimals: 2,
          locale: 'en-US',
          storedAsCents: true,
        },
      }

      moneyColumnRenderer.configure(column)

      expect(column.render(12345, 'display')).toBe('$123.45')
    })

    it('formats decimal values when amounts are not stored as cents', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          currency: 'USD',
          decimals: 0,
          locale: 'en-US',
          storedAsCents: false,
        },
      }

      moneyColumnRenderer.configure(column)

      expect(column.render(123, 'display')).toBe('$123')
    })

    it('returns numeric values for sort and type modes', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          storedAsCents: true,
        },
      }

      moneyColumnRenderer.configure(column)

      expect(column.render(12345, 'sort')).toBe(123.45)
      expect(column.render('12345', 'type')).toBe(123.45)
    })

    it('returns an empty string for blank values', () => {
      const column: Record<string, any> = { customOptions: { isMoney: true } }

      moneyColumnRenderer.configure(column)

      expect(column.render(null, 'display')).toBe('')
      expect(column.render(undefined, 'display')).toBe('')
      expect(column.render('', 'display')).toBe('')
    })

    it('escapes non-numeric display values', () => {
      const column: Record<string, any> = { customOptions: { isMoney: true } }

      moneyColumnRenderer.configure(column)

      expect(column.render('<b>free</b>', 'display')).toBe('&lt;b&gt;free&lt;/b&gt;')
    })

    it('falls back to safe defaults for invalid options', () => {
      const column: Record<string, any> = {
        customOptions: {
          isMoney: true,
          currency: 'INVALID',
          decimals: 99,
          locale: 'en-US',
        },
      }

      moneyColumnRenderer.configure(column)

      const expected = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(123.45)

      expect(column.render(12345, 'display')).toBe(expected)
    })
  })
})
