import { describe, expect, it } from 'vitest'
import { createChoiceColumnRenderer } from '../src/columnRenderers/choiceColumnRenderer'
import { BootstrapColumnStyleAdapter } from '../src/columnStyles/BootstrapColumnStyleAdapter'
import { TailwindColumnStyleAdapter } from '../src/columnStyles/TailwindColumnStyleAdapter'

const bootstrapStyle = new BootstrapColumnStyleAdapter()
const tailwindStyle = new TailwindColumnStyleAdapter()

describe('ChoiceColumn render', () => {
    it('renders the escaped label when badges are disabled', () => {
        const column: Record<string, any> = {
            customOptions: {
                choices: {
                    active: 'Active <b>',
                },
            },
        }

        createChoiceColumnRenderer(bootstrapStyle).configure(column)

        expect(column.render('active', 'display')).toBe('Active &lt;b&gt;')
    })

    it('renders a Bootstrap badge with the mapped variant for display mode', () => {
        const column: Record<string, any> = {
            customOptions: {
                choices: {
                    active: 'Active',
                },
                renderAsBadges: {
                    active: 'success',
                },
                defaultBadgeVariant: 'warning',
            },
        }

        createChoiceColumnRenderer(bootstrapStyle).configure(column)

        expect(column.render('active', 'display')).toBe(
            '<span class="badge text-bg-success">Active</span>'
        )
    })

    it('falls back to the default badge variant for unmapped values', () => {
        const column: Record<string, any> = {
            customOptions: {
                choices: {
                    pending: 'Pending',
                },
                renderAsBadges: {},
                defaultBadgeVariant: 'warning',
            },
        }

        createChoiceColumnRenderer(bootstrapStyle).configure(column)

        expect(column.render('pending', 'display')).toBe(
            '<span class="badge text-bg-warning">Pending</span>'
        )
    })

    it('renders a Tailwind badge when using the Tailwind style adapter', () => {
        const column: Record<string, any> = {
            customOptions: {
                choices: {
                    active: 'Active',
                },
                renderAsBadges: {
                    active: 'success',
                },
            },
        }

        createChoiceColumnRenderer(tailwindStyle).configure(column)

        expect(column.render('active', 'display')).toBe(
            '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800">Active</span>'
        )
    })

    it('returns the plain label outside display mode', () => {
        const column: Record<string, any> = {
            customOptions: {
                choices: {
                    active: 'Active',
                },
                renderAsBadges: {
                    active: 'success',
                },
            },
        }

        createChoiceColumnRenderer(bootstrapStyle).configure(column)

        expect(column.render('active', 'filter')).toBe('Active')
    })

    it('matches columns with choices in customOptions', () => {
        const renderer = createChoiceColumnRenderer(bootstrapStyle)

        expect(renderer.matches({ customOptions: { choices: { a: 'A' } } })).toBe(true)
    })

    it('does not match columns without customOptions choices', () => {
        const renderer = createChoiceColumnRenderer(bootstrapStyle)

        expect(renderer.matches({ data: 'name' })).toBe(false)
        expect(renderer.matches({})).toBe(false)
    })
})
