// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ensurePolling, isPollingEnabled, startPolling } from '../polling.js'

function createTable() {
    const handlers = new Map<string, Array<() => void>>()
    const reload = vi.fn()

    return {
        reload,
        fire: (event: string) => {
            for (const [names, callbacks] of handlers) {
                if (names.split(' ').includes(event)) {
                    for (const callback of callbacks) {
                        callback()
                    }
                }
            }
        },
        table: {
            ajax: { reload },
            on: (event: string, callback: () => void) => {
                handlers.set(event, [...(handlers.get(event) ?? []), callback])
            },
            off: (event: string, callback?: () => void) => {
                handlers.set(
                    event,
                    (handlers.get(event) ?? []).filter((registered) => registered !== callback)
                )
            },
        },
    }
}

function setVisibility(state: 'visible' | 'hidden'): void {
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: state })
    document.dispatchEvent(new Event('visibilitychange'))
}

describe('startPolling', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        setVisibility('visible')
    })

    afterEach(() => {
        vi.useRealTimers()
        document.body.innerHTML = ''
    })

    it('reloads on every interval without resetting paging', () => {
        const { table, reload } = createTable()
        startPolling(table, 5, document.body)

        vi.advanceTimersByTime(4999)
        expect(reload).not.toHaveBeenCalled()

        vi.advanceTimersByTime(1)
        expect(reload).toHaveBeenCalledWith(null, false)

        vi.advanceTimersByTime(5000)
        expect(reload).toHaveBeenCalledTimes(2)
    })

    it('skips ticks while the tab is hidden and reloads when it becomes visible', () => {
        const { table, reload } = createTable()
        startPolling(table, 5, document.body)

        setVisibility('hidden')
        vi.advanceTimersByTime(12000)
        expect(reload).not.toHaveBeenCalled()

        setVisibility('visible')
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('does not reload on becoming visible before the interval elapsed', () => {
        const { table, reload } = createTable()
        startPolling(table, 5, document.body)

        setVisibility('hidden')
        vi.advanceTimersByTime(1000)
        setVisibility('visible')

        expect(reload).not.toHaveBeenCalled()
    })

    it('pauses while a modal is open and resumes once it is closed', () => {
        const { table, reload } = createTable()
        startPolling(table, 5, document.body)
        const modal = document.createElement('dialog')
        modal.setAttribute('data-ux-datatables-modal', '')
        document.body.appendChild(modal)

        vi.advanceTimersByTime(5000)
        expect(reload).not.toHaveBeenCalled()

        modal.remove()
        vi.advanceTimersByTime(5000)
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('pauses while the viewer is typing in the search field', () => {
        const { table, reload } = createTable()
        document.body.innerHTML = '<div class="dt-search"><input type="search"></div>'
        const input = document.querySelector('input') as HTMLInputElement
        startPolling(table, 5, document.body)

        input.focus()
        vi.advanceTimersByTime(5000)
        expect(reload).not.toHaveBeenCalled()

        input.blur()
        vi.advanceTimersByTime(5000)
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('never overlaps requests', () => {
        const { table, reload, fire } = createTable()
        startPolling(table, 5, document.body)

        fire('preXhr.dt')
        vi.advanceTimersByTime(10000)
        expect(reload).not.toHaveBeenCalled()

        fire('xhr.dt')
        vi.advanceTimersByTime(5000)
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('frees the request slot when the request fails', () => {
        const { table, reload, fire } = createTable()
        startPolling(table, 5, document.body)

        fire('preXhr.dt')
        fire('xhr.dt')
        vi.advanceTimersByTime(5000)

        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('stops everything when the returned function is called', () => {
        const { table, reload } = createTable()
        const stop = startPolling(table, 5, document.body)

        stop()
        vi.advanceTimersByTime(20000)
        setVisibility('hidden')
        setVisibility('visible')

        expect(reload).not.toHaveBeenCalled()
        expect(vi.getTimerCount()).toBe(0)
    })
})

describe('isPollingEnabled', () => {
    it('requires a positive numeric interval', () => {
        expect(isPollingEnabled({ pollInterval: 10 })).toBe(true)
        expect(isPollingEnabled({ pollInterval: 0 })).toBe(false)
        expect(isPollingEnabled({ pollInterval: '10' })).toBe(false)
        expect(isPollingEnabled({})).toBe(false)
    })
})

describe('ensurePolling', () => {
    const payload = { pollInterval: 5 }

    beforeEach(() => {
        vi.useFakeTimers()
        setVisibility('visible')
        document.body.innerHTML = '<table id="t"></table>'
    })

    afterEach(() => {
        vi.useRealTimers()
        document.body.innerHTML = ''
    })

    function options(table: ReturnType<typeof createTable>['table'] | null, mercure = false) {
        return {
            table,
            payload,
            element: document.getElementById('t') as HTMLElement,
            mercureEnabled: mercure,
        }
    }

    it('starts after connect and stops on disconnect', () => {
        const { table, reload } = createTable()
        const stop = ensurePolling(null, options(table))

        vi.advanceTimersByTime(5000)
        expect(reload).toHaveBeenCalledTimes(1)

        stop?.()
        vi.advanceTimersByTime(10000)
        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('restarts on reconnect once stopped', () => {
        const { table, reload } = createTable()
        ensurePolling(null, options(table))?.()

        ensurePolling(null, options(table))
        vi.advanceTimersByTime(5000)

        expect(reload).toHaveBeenCalledTimes(1)
    })

    it('does not start a second timer while one runs', () => {
        const { table, reload } = createTable()
        const first = ensurePolling(null, options(table))
        const second = ensurePolling(first, options(table))

        vi.advanceTimersByTime(5000)

        expect(second).toBe(first)
        expect(reload).toHaveBeenCalledTimes(1)
        expect(vi.getTimerCount()).toBe(1)
    })

    it('does not start on a detached element', () => {
        const { table } = createTable()
        const opts = options(table)
        opts.element.remove()

        expect(ensurePolling(null, opts)).toBeNull()
        expect(vi.getTimerCount()).toBe(0)
    })

    it('does not start without an Ajax source or a poll interval', () => {
        const { table } = createTable()

        expect(ensurePolling(null, options({ ...table, ajax: undefined }))).toBeNull()
        expect(ensurePolling(null, { ...options(table), payload: {} })).toBeNull()
        expect(ensurePolling(null, options(null))).toBeNull()
    })

    it('warns about Mercure only once', () => {
        const { table } = createTable()
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)

        ensurePolling(null, options(table, true))?.()
        ensurePolling(null, options(table, true))?.()

        expect(warn).toHaveBeenCalledTimes(1)
        warn.mockRestore()
    })
})
