import { afterEach, describe, expect, it, vi } from 'vitest'
import { applyCustomButtonActions } from '../applyCustomButtonActions.js'
import { buttonActions } from '../buttonActionRegistry.js'

describe('applyCustomButtonActions', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('replaces a registered custom button action with the real callback', () => {
        const action = vi.fn()
        buttonActions.register('restoreOrder', action)

        const payload = {
            layout: {
                topStart: {
                    buttons: [{ action: 'restoreOrder', text: 'Restore order' }],
                },
            },
        }

        applyCustomButtonActions(payload)

        const button = (payload.layout.topStart.buttons[0] as unknown as Record<string, unknown>)
        expect(button.action).toBe(action)
        expect(button.text).toBe('Restore order')
    })

    it('logs an error and leaves a safe no-op when the action name is unregistered', () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

        const payload = {
            layout: {
                topStart: {
                    buttons: [{ action: 'neverRegistered' }],
                },
            },
        }

        applyCustomButtonActions(payload)

        const button = (payload.layout.topStart.buttons[0] as unknown as Record<string, unknown>)
        expect(typeof button.action).toBe('function')
        expect(() => (button.action as () => void)()).not.toThrow()
        expect(errorSpy).toHaveBeenCalledWith(
            'No button action registered for "neverRegistered"'
        )
    })

    it('leaves predefined and bare-string buttons untouched', () => {
        const payload = {
            layout: {
                topStart: {
                    buttons: [
                        'colvis',
                        { extend: 'csv', text: 'Export CSV' },
                    ],
                },
                topEnd: 'search',
            },
        }

        const before = JSON.stringify(payload)
        applyCustomButtonActions(payload)

        expect(JSON.stringify(payload)).toBe(before)
    })

    it('resolves a custom action nested inside a colvis dropdown postfixButtons entry', () => {
        const action = vi.fn()
        buttonActions.register('restoreOrder', action)

        const payload = {
            layout: {
                topStart: {
                    buttons: [
                        {
                            extend: 'colvis',
                            text: 'Columns',
                            postfixButtons: [
                                { extend: 'colvisRestore' },
                                { action: 'restoreOrder', text: 'Restore order' },
                            ],
                        },
                    ],
                },
            },
        }

        applyCustomButtonActions(payload)

        const colvis = (payload.layout.topStart.buttons[0] as unknown as Record<string, unknown>)
        const postfix = colvis.postfixButtons as Record<string, unknown>[]
        expect(postfix[0].extend).toBe('colvisRestore')
        expect(postfix[0].action).toBeUndefined()
        expect(postfix[1].action).toBe(action)
        expect(postfix[1].text).toBe('Restore order')
    })

    it('resolves nested layout groups (arrays of items within one position)', () => {
        const action = vi.fn()
        buttonActions.register('clearFilters', action)

        const payload = {
            layout: {
                topStart: ['search', { buttons: [{ action: 'clearFilters' }] }],
            },
        }

        applyCustomButtonActions(payload)

        const group = (payload.layout.topStart[1] as unknown as Record<string, unknown>)
        const buttons = group.buttons as Record<string, unknown>[]
        expect(buttons[0].action).toBe(action)
    })

    it('does nothing when the payload has no layout', () => {
        const payload: Record<string, unknown> = {}

        expect(() => applyCustomButtonActions(payload)).not.toThrow()
        expect(payload).toEqual({})
    })
})
