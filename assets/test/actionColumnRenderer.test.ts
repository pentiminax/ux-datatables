import { describe, expect, it } from 'vitest'
import {
  actionColumnRenderer,
  createActionColumnRenderer,
} from '../src/columnRenderers/actionColumnRenderer'

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
            className: 'btn btn-danger',
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

    it('disables delete buttons when mutations are unavailable', () => {
      const renderer = createActionColumnRenderer(false)
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            name: 'DELETE',
            label: 'Delete',
            className: 'btn btn-danger',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
          },
        ],
      }

      renderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('disabled')
      expect(html).toContain('aria-disabled="true"')
    })

    it('returns empty string for non-display types', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            className: 'btn btn-danger',
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
            className: 'btn btn-danger',
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
            className: 'btn btn-danger',
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
            className: 'btn btn-primary',
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

    it('renders a collapsible detail action as a button with entity and id', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'Details',
            className: 'btn btn-link',
            idField: 'id',
            entityClass: 'App\\Entity\\Book',
            collapsible: true,
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('<button ')
      expect(html).toContain('type="button"')
      expect(html).toContain('data-action-type="DETAIL"')
      expect(html).toContain('data-entity="App\\Entity\\Book"')
      expect(html).toContain('data-id="42"')
      expect(html).not.toContain('href=')
    })

    it('uses a default control icon for a collapsible detail action without an icon', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: '',
            className: 'btn',
            idField: 'id',
            collapsible: true,
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('dtr-control-icon')
    })

    it('renders custom html attributes for detail actions', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            className: 'btn btn-primary',
            idField: 'id',
            url: '/books/42',
            htmlAttributes: {
              target: '_blank',
              rel: 'noopener noreferrer',
              disabled: true,
              hidden: false,
            },
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('target="_blank"')
      expect(html).toContain('rel="noopener noreferrer"')
      expect(html).toContain(' disabled')
      expect(html).not.toContain(' hidden')
    })

    it('renders detail action from row-resolved metadata', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            name: 'DETAIL',
            label: 'View',
            className: 'btn btn-primary',
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

    it('renders a custom action as a link from per-row resolved url', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'CUSTOM',
            name: 'download',
            label: 'Download',
            className: '',
            idField: 'id',
          },
          {
            type: 'CUSTOM',
            name: 'view',
            label: 'View',
            className: '',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        id: 42,
        __ux_datatables_actions: {
          download: { url: '/invoices/42/download' },
          view: { url: '/invoices/42' },
        },
      })

      expect(html).toContain('<a ')
      expect(html).toContain('href="/invoices/42/download"')
      expect(html).toContain('Download')
      expect(html).toContain('href="/invoices/42"')
      expect(html).toContain('View')
      expect(html).toContain('data-action-type="CUSTOM"')
    })

    it('renders button id from row-resolved action metadata when row id is missing', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'EDIT',
            name: 'EDIT',
            label: 'Edit',
            className: 'btn btn-warning',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        __ux_datatables_actions: {
          EDIT: {
            id: 42,
          },
        },
      })

      expect(html).toContain('data-action-type="EDIT"')
      expect(html).toContain('data-id="42"')
    })

    it('prefers row id over row-resolved action metadata', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'EDIT',
            label: 'Edit',
            className: 'btn btn-warning',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        id: 7,
        __ux_datatables_actions: {
          EDIT: {
            id: 42,
          },
        },
      })

      expect(html).toContain('data-id="7"')
      expect(html).not.toContain('data-id="42"')
    })

    it('escapes button id resolved from action metadata', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'EDIT',
            name: 'EDIT',
            label: 'Edit',
            className: 'btn btn-warning',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        __ux_datatables_actions: {
          EDIT: {
            id: 'abc"<script>',
          },
        },
      })

      expect(html).toContain('data-id="abc&quot;&lt;script&gt;"')
    })

    it('hides detail action when url is missing', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            className: 'btn btn-primary',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      expect(column.render(null, 'display', { id: 42 })).toBe('')
    })

    it('hides detail action when the resolved url is unsafe', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            name: 'DETAIL',
            label: 'View',
            className: 'btn btn-primary',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', {
        id: 42,
        __ux_datatables_actions: {
          DETAIL: {
            url: 'javascript:alert(1)',
          },
        },
      })

      expect(html).toBe('')
    })

    it('renders icon when set', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            className: 'btn btn-danger',
            idField: 'id',
            icon: 'bi bi-trash',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).toContain('<i class="bi bi-trash"></i>')
    })

    it('renders custom html attributes for button actions and ignores reserved ones', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            className: 'btn btn-danger',
            entityClass: 'App\\Entity\\User',
            idField: 'id',
            htmlAttributes: {
              target: '_blank',
              class: 'ignored-class',
              'data-id': '999',
              'aria-label': 'Delete row',
            },
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('target="_blank"')
      expect(html).toContain('aria-label="Delete row"')
      expect(html).toContain('class="btn btn-danger"')
      expect(html).toContain('data-id="42"')
      expect(html).not.toContain('ignored-class')
      expect(html).not.toContain('data-id="999"')
    })

    it('escapes HTML in rendered output', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: '<script>alert("xss")</script>',
            className: 'btn',
            idField: 'id',
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 1 })
      expect(html).not.toContain('<script>')
      expect(html).toContain('&lt;script&gt;')
    })

    it('escapes HTML in custom html attributes and ignores invalid attribute names', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DETAIL',
            label: 'View',
            className: 'btn btn-primary',
            idField: 'id',
            url: '/books/42',
            htmlAttributes: {
              title: '"quoted"',
              'onclick bad': 'alert(1)',
            },
          },
        ],
      }

      actionColumnRenderer.configure(column)

      const html = column.render(null, 'display', { id: 42 })
      expect(html).toContain('title="&quot;quoted&quot;"')
      expect(html).not.toContain('onclick bad')
    })

    it('uses custom idField', () => {
      const column: Record<string, any> = {
        actions: [
          {
            type: 'DELETE',
            label: 'Delete',
            className: 'btn btn-danger',
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
            className: 'btn btn-danger',
            idField: 'id',
          },
          {
            type: 'DELETE',
            label: 'Force Delete',
            className: 'btn btn-warning',
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
            className: 'btn btn-danger',
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
