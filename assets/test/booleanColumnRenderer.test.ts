import { describe, expect, it } from 'vitest'
import { createBooleanColumnRenderer } from '../src/columnRenderers/booleanColumnRenderer'

const TOGGLE_URL = '/datatables/ajax/edit'

describe('booleanColumnRenderer', () => {
  it('matches columns with renderAsSwitch set to true', () => {
    const renderer = createBooleanColumnRenderer(TOGGLE_URL)
    expect(renderer.matches({ customOptions: { renderAsSwitch: true } })).toBe(true)
    expect(renderer.matches({ customOptions: { renderAsSwitch: false } })).toBe(false)
    expect(renderer.matches({})).toBe(false)
  })

  describe('configure', () => {
    it('sets column type to num if not defined', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      expect(column.type).toBe('num')
    })

    it('does not override an existing column type', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true }, type: 'string' }
      renderer.configure(column)
      expect(column.type).toBe('string')
    })

    it('returns 1 for sort type when value is truthy', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      expect(column.render(true, 'sort', {})).toBe(1)
      expect(column.render('1', 'sort', {})).toBe(1)
    })

    it('returns 0 for sort type when value is falsy', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      expect(column.render(false, 'sort', {})).toBe(0)
      expect(column.render('0', 'sort', {})).toBe(0)
    })

    it('returns 1/0 for type mode', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      expect(column.render(1, 'type', {})).toBe(1)
      expect(column.render(0, 'type', {})).toBe(0)
    })

    it('returns ON/OFF for filter mode', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      expect(column.render(true, 'filter', {})).toBe('ON')
      expect(column.render(false, 'filter', {})).toBe('OFF')
    })

    it('renders a checked switch for display mode when value is true', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = {
        customOptions: {
          renderAsSwitch: true,
          entityClass: 'App\\Entity\\User',
        },
        data: 'active',
      }
      renderer.configure(column)
      const html = column.render(true, 'display', { id: 42 })
      expect(html).toContain('checked')
      expect(html).toContain('data-id="42"')
      expect(html).toContain(`data-url="${TOGGLE_URL}"`)
      expect(html).toContain('data-field="active"')
      expect(html).toContain('data-entity="App\\Entity\\User"')
    })

    it('renders an unchecked switch for display mode when value is false', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = {
        customOptions: {
          renderAsSwitch: true,
          entityClass: 'App\\Entity\\User',
        },
        data: 'active',
      }
      renderer.configure(column)
      const html = column.render(false, 'display', { id: 1 })
      expect(html).not.toContain('checked')
    })

    it('renders a disabled switch when entity class is empty', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
      renderer.configure(column)
      const html = column.render(true, 'display', { id: 1 })
      expect(html).toContain('disabled')
    })

    it('uses defaultState as fallback for null data', () => {
      const renderer = createBooleanColumnRenderer(TOGGLE_URL)
      const column: Record<string, any> = {
        customOptions: {
          renderAsSwitch: true,
          defaultState: true,
        },
      }
      renderer.configure(column)
      expect(column.render(null, 'sort', {})).toBe(1)
    })
  })
})
