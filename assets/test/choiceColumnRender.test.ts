import { describe, expect, it } from 'vitest'
import { choiceColumnRenderer } from '../src/columnRenderers/choiceColumnRenderer'

describe('ChoiceColumn render', () => {
  it('renders the escaped label when badges are disabled', () => {
    const column: Record<string, any> = {
      choices: {
        active: 'Active <b>',
      },
    }

    choiceColumnRenderer.configure(column)

    expect(column.render('active', 'display')).toBe('Active &lt;b&gt;')
  })

  it('renders a badge with the mapped variant for display mode', () => {
    const column: Record<string, any> = {
      choices: {
        active: 'Active',
      },
      renderAsBadges: {
        active: 'success',
      },
      defaultBadgeVariant: 'warning',
    }

    choiceColumnRenderer.configure(column)

    expect(column.render('active', 'display')).toBe(
      '<span class="badge text-bg-success">Active</span>'
    )
  })

  it('falls back to the default badge variant for unmapped values', () => {
    const column: Record<string, any> = {
      choices: {
        pending: 'Pending',
      },
      renderAsBadges: {},
      defaultBadgeVariant: 'warning',
    }

    choiceColumnRenderer.configure(column)

    expect(column.render('pending', 'display')).toBe(
      '<span class="badge text-bg-warning">Pending</span>'
    )
  })

  it('returns the plain label outside display mode', () => {
    const column: Record<string, any> = {
      choices: {
        active: 'Active',
      },
      renderAsBadges: {
        active: 'success',
      },
    }

    choiceColumnRenderer.configure(column)

    expect(column.render('active', 'filter')).toBe('Active')
  })
})
