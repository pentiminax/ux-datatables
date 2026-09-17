import { describe, expect, it } from 'vitest'
import { createBooleanColumnRenderer } from '../src/columnRenderers/booleanColumnRenderer'
import { BootstrapColumnStyleAdapter } from '../src/columnStyles/BootstrapColumnStyleAdapter'
import { TailwindColumnStyleAdapter } from '../src/columnStyles/TailwindColumnStyleAdapter'

const TOGGLE_URL = '/datatables/ajax/edit'
const bootstrapStyle = new BootstrapColumnStyleAdapter()
const tailwindStyle = new TailwindColumnStyleAdapter()

function createRenderer(mutationsEnabled = true, style = bootstrapStyle) {
    return createBooleanColumnRenderer(TOGGLE_URL, mutationsEnabled, style)
}

describe('booleanColumnRenderer', () => {
    it('matches columns with renderAsSwitch set to true', () => {
        const renderer = createRenderer()
        expect(renderer.matches({ customOptions: { renderAsSwitch: true } })).toBe(true)
        expect(renderer.matches({ customOptions: { renderAsSwitch: false } })).toBe(false)
        expect(renderer.matches({})).toBe(false)
    })

    describe('configure', () => {
        it('sets column type to num if not defined', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            expect(column.type).toBe('num')
        })

        it('does not override an existing column type', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
                type: 'string',
            }
            renderer.configure(column)
            expect(column.type).toBe('string')
        })

        it('returns 1 for sort type when value is truthy', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            expect(column.render(true, 'sort', {})).toBe(1)
            expect(column.render('1', 'sort', {})).toBe(1)
        })

        it('returns 0 for sort type when value is falsy', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            expect(column.render(false, 'sort', {})).toBe(0)
            expect(column.render('0', 'sort', {})).toBe(0)
        })

        it('returns 1/0 for type mode', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            expect(column.render(1, 'type', {})).toBe(1)
            expect(column.render(0, 'type', {})).toBe(0)
        })

        it('returns ON/OFF for filter mode', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            expect(column.render(true, 'filter', {})).toBe('ON')
            expect(column.render(false, 'filter', {})).toBe('OFF')
        })

        it('renders a checked Bootstrap switch for display mode when value is true', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', { id: 42 })
            expect(html).toContain('form-check form-switch')
            expect(html).toContain('form-check-input boolean-switch-action')
            expect(html).toContain('checked')
            expect(html).toContain('data-id="42"')
            expect(html).toContain(`data-url="${TOGGLE_URL}"`)
            expect(html).toContain('data-field="active"')
            expect(html).not.toContain('data-entity=')
        })

        it('renders a Tailwind switch when using the Tailwind style adapter', () => {
            const renderer = createRenderer(true, tailwindStyle)
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', { id: 42 })
            expect(html).toContain('peer sr-only boolean-switch-action')
            expect(html).toContain('peer-checked:bg-blue-600')
            expect(html).toContain('checked')
            expect(html).toContain('data-id="42"')
            expect(html).not.toContain('form-check')
        })

        it('prefers row-resolved boolean switch metadata id over row id', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', {
                id: 42,
                __ux_datatables_boolean_switches: {
                    active: 'user-uuid-42',
                },
            })
            expect(html).toContain('data-id="user-uuid-42"')
            expect(html).not.toContain('data-id="42"')
            expect(html).toContain('data-field="active"')
            expect(html).not.toContain('data-entity=')
        })

        it('renders an unchecked switch for display mode when value is false', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(false, 'display', { id: 1 })
            expect(html).not.toContain('checked')
        })

        it('renders a disabled switch when row id is missing', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            const html = column.render(true, 'display', {})
            expect(html).toContain('disabled')
            expect(html).toContain('data-id=""')
            expect(html).not.toContain('data-entity=')
        })

        it('renders a disabled switch when row id is an empty string', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = { customOptions: { renderAsSwitch: true } }
            renderer.configure(column)
            const html = column.render(true, 'display', { id: '' })
            expect(html).toContain('disabled')
        })

        it('renders a disabled switch when mutations are unavailable', () => {
            const renderer = createRenderer(false)
            const column: Record<string, any> = {
                customOptions: { renderAsSwitch: true },
            }
            renderer.configure(column)
            const html = column.render(true, 'display', { id: 1 })
            expect(html).toContain('disabled')
        })

        it('uses custom toggle id field as fallback when metadata is unavailable', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: {
                    renderAsSwitch: true,
                    toggleIdField: 'uuid',
                },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', { id: 42, uuid: 'user-uuid-42' })
            expect(html).toContain('data-id="user-uuid-42"')
        })

        it('falls back to toggle id field when metadata id is an empty string', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: {
                    renderAsSwitch: true,
                    toggleIdField: 'uuid',
                },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', {
                uuid: 'user-uuid-42',
                __ux_datatables_boolean_switches: {
                    active: '',
                },
            })
            expect(html).toContain('data-id="user-uuid-42"')
            expect(html).not.toContain('disabled')
        })

        it('uses toggleField as switch metadata key and submitted field', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: {
                    renderAsSwitch: true,
                    toggleField: 'enabled',
                },
                data: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', {
                id: 42,
                __ux_datatables_boolean_switches: {
                    enabled: 'user-uuid-42',
                },
            })
            expect(html).toContain('data-id="user-uuid-42"')
            expect(html).toContain('data-field="enabled"')
        })

        it('falls back to the column field when toggleField is empty', () => {
            const renderer = createRenderer()
            const column: Record<string, any> = {
                customOptions: {
                    renderAsSwitch: true,
                    toggleField: '',
                },
                field: 'active',
            }
            renderer.configure(column)
            const html = column.render(true, 'display', {
                __ux_datatables_boolean_switches: {
                    active: 42,
                },
            })
            expect(html).toContain('data-id="42"')
            expect(html).toContain('data-field="active"')
        })

        it('uses defaultState as fallback for null data', () => {
            const renderer = createRenderer()
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
