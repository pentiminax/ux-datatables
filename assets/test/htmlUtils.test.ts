import { describe, expect, it } from 'vitest'
import { escapeHtml, isUnsafeUrl, parseBooleanValue } from '../src/functions/htmlUtils'

describe('escapeHtml', () => {
  it('escapes ampersands', () => {
    expect(escapeHtml('a & b')).toBe('a &amp; b')
  })

  it('escapes angle brackets', () => {
    expect(escapeHtml('<script>')).toBe('&lt;script&gt;')
  })

  it('escapes double quotes', () => {
    expect(escapeHtml('"hello"')).toBe('&quot;hello&quot;')
  })

  it('escapes single quotes', () => {
    expect(escapeHtml("it's")).toBe('it&#039;s')
  })

  it('returns plain strings unchanged', () => {
    expect(escapeHtml('hello world')).toBe('hello world')
  })
})

describe('parseBooleanValue', () => {
  it('returns defaultValue for null', () => {
    expect(parseBooleanValue(null)).toBe(false)
    expect(parseBooleanValue(null, true)).toBe(true)
  })

  it('returns defaultValue for undefined', () => {
    expect(parseBooleanValue(undefined)).toBe(false)
    expect(parseBooleanValue(undefined, true)).toBe(true)
  })

  it('returns defaultValue for empty string', () => {
    expect(parseBooleanValue('')).toBe(false)
    expect(parseBooleanValue('', true)).toBe(true)
  })

  it('returns the boolean value as-is', () => {
    expect(parseBooleanValue(true)).toBe(true)
    expect(parseBooleanValue(false)).toBe(false)
  })

  it('treats 0 as false and non-zero as true', () => {
    expect(parseBooleanValue(0)).toBe(false)
    expect(parseBooleanValue(1)).toBe(true)
    expect(parseBooleanValue(-1)).toBe(true)
  })

  it('parses truthy string values', () => {
    for (const v of ['1', 'true', 'yes', 'y', 'on']) {
      expect(parseBooleanValue(v)).toBe(true)
    }
  })

  it('parses falsy string values', () => {
    for (const v of ['0', 'false', 'no', 'off', 'random']) {
      expect(parseBooleanValue(v)).toBe(false)
    }
  })

  it('is case-insensitive for string values', () => {
    expect(parseBooleanValue('TRUE')).toBe(true)
    expect(parseBooleanValue('YES')).toBe(true)
  })

  it('returns false for unknown types', () => {
    expect(parseBooleanValue({})).toBe(false)
    expect(parseBooleanValue([])).toBe(false)
  })
})

describe('isUnsafeUrl', () => {
  it('flags javascript: URLs', () => {
    expect(isUnsafeUrl('javascript:alert(1)')).toBe(true)
  })

  it('flags data: URLs', () => {
    expect(isUnsafeUrl('data:text/html,<h1>test</h1>')).toBe(true)
  })

  it('is case-insensitive', () => {
    expect(isUnsafeUrl('JAVASCRIPT:alert(1)')).toBe(true)
    expect(isUnsafeUrl('DATA:text/plain,hi')).toBe(true)
  })

  it('trims leading whitespace before checking', () => {
    expect(isUnsafeUrl('  javascript:void(0)')).toBe(true)
  })

  it('allows safe URLs', () => {
    expect(isUnsafeUrl('https://example.com')).toBe(false)
    expect(isUnsafeUrl('/relative/path')).toBe(false)
    expect(isUnsafeUrl('http://example.com')).toBe(false)
  })
})
