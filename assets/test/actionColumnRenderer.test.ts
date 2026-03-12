import { describe, expect, it } from 'vitest'
import { actionColumnRenderer } from '../src/columnRenderers/actionColumnRenderer'

describe('actionColumnRenderer', () => {
  it('matches columns with actions array', () => {
    expect(actionColumnRenderer.matches({ actions: [] })).toBe(true)
    expect(actionColumnRenderer.matches({ actions: [{ type: 'DELETE' }] })).toBe(true)
  })

  it('does not match columns without actions array', () => {
    expect(actionColumnRenderer.matches({})).toBe(false)
    expect(actionColumnRenderer.matches({ action: 'DELETE' })).toBe(false)
    expect(actionColumnRenderer.matches({ actions: 'not-array' })).toBe(false)
  })

  describe('configure', () => {
    it('renders a delete button for display type', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('data-action-type="DELETE"')
      expect(html).toContain('data-entity="App\\Entity\\User"')
      expect(html).toContain('data-id="42"')
      expect(html).toContain('Delete')
      expect(html).toContain('btn btn-danger')
    })

    it('returns empty string for non-display types', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      expect(column.render(null, 'sort', {})).toBe('')
      expect(column.render(null, 'filter', {})).toBe('')
      expect(column.render(null, 'type', {})).toBe('')
    })

    it('filters actions based on displayCondition', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
            displayCondition: { field: 'isDeletable', value: true },
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const htmlVisible = column.render(null, 'display', { id: 1, isDeletable: true })
      expect(htmlVisible).toContain('Delete')

      const htmlHidden = column.render(null, 'display', { id: 2, isDeletable: false })
      expect(htmlHidden).toBe('')
    })

    it('renders confirm attribute when set', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            idField: 'id',
            confirm: 'Are you sure?',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('data-confirm="Are you sure?"')
    })

    it('renders detail action as a link with static url', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            cssClass: 'btn btn-primary',
            idField: 'id',
            url: '/books/42',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('<a ')
      expect(html).toContain('href="/books/42"')
      expect(html).toContain('data-action-type="DETAIL"')
      expect(html).toContain('View')
    })

    it('renders detail action from row-resolved metadata', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            cssClass: 'btn btn-primary',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        id: 42,
        __ux_datatables_actions: {
          DETAIL: {
            url: '/books/42',
          },
        },
      })

      expect(html).toContain('href="/books/42"')
    })

    it('hides detail action when url is missing', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            cssClass: 'btn btn-primary',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      expect(column.render(null, 'display', { id: 42 })).toBe('')
    })

    it('renders icon when set', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            idField: 'id',
            icon: 'bi bi-trash',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('<i class="bi bi-trash"></i>')
    })

    it('escapes HTML in rendered output', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: '<script>alert("xss")</script>',
            cssClass: 'btn',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).not.toContain('<script>')
      expect(html).toContain('&lt;script&gt;')
    })

    it('uses custom idField', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            entityClass: 'App\\Entity\\User',
            idField: 'uuid',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { uuid: 'abc-123', id: 99 })
      expect(html).toContain('data-id="abc-123"')
    })

    it('renders multiple actions', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            idField: 'id',
          },
          {
            type: 'DELETE',
            label: 'Force Delete',
            cssClass: 'btn btn-warning',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('Delete')
      expect(html).toContain('Force Delete')
    })

    it('shows all actions when no displayCondition set', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            cssClass: 'btn btn-danger',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('Delete')
    })
  })
})
