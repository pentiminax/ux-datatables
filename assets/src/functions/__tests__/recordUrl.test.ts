import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { bindRecordClick, isSafeRecordUrl } from '../recordUrl.js'

describe('isSafeRecordUrl', () => {
    it.each([
        ['/users/42?a=1#b', true],
        ['/', true],
        ['http://example.com/x', true],
        ['HTTPS://example.com', true],
        ['//evil.example', false],
        ['/\\evil.example', false],
        ['javascript:alert(1)', false],
        ['JaVaScRiPt:alert(1)', false],
        ['java\tscript:alert(1)', false],
        [' javascript:alert(1)', false],
        ['data:text/html,x', false],
        ['vbscript:x', false],
        ['file:///etc/passwd', false],
        ['users/42', false],
        ['http://', false],
        ['/a\nb', false],
        ['', false],
        [null, false],
        [42, false],
    ])('%j -> %s', (url, expected) => {
        expect(isSafeRecordUrl(url)).toBe(expected)
    })
})

describe('bindRecordClick', () => {
    const assign = vi.fn()
    let table: HTMLElement

    function click(target: Element, init: MouseEventInit = {}): void {
        target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ...init }))
    }

    beforeEach(() => {
        assign.mockClear()
        vi.stubGlobal('location', { assign })
        table = document.createElement('table')
        table.innerHTML =
            '<tbody>' +
            '<tr data-record-url="/users/1"><td class="plain">Ann</td>' +
            '<td><a href="/x">link</a></td><td><button>b</button></td>' +
            '<td><input type="checkbox"></td><td class="dt-select">s</td>' +
            '<td><span data-action-type="delete"><i></i></span></td>' +
            '<td><label>l</label></td></tr>' +
            '<tr data-record-url="javascript:alert(1)"><td class="evil">Eve</td></tr>' +
            '<tr><td class="none">None</td></tr>' +
            '</tbody>'
        document.body.appendChild(table)
        bindRecordClick(table)
    })

    afterEach(() => {
        table.remove()
        vi.unstubAllGlobals()
    })

    it('navigates when a plain cell is clicked', () => {
        click(table.querySelector('.plain') as Element)

        expect(assign).toHaveBeenCalledWith('/users/1')
    })

    it.each(['a', 'button', 'input', '.dt-select', '[data-action-type] i', 'label'])(
        'ignores clicks on %s',
        (selector) => {
            click(table.querySelector(selector) as Element)

            expect(assign).not.toHaveBeenCalled()
        }
    )

    it('refuses an unsafe URL and rows without one', () => {
        click(table.querySelector('.evil') as Element)
        click(table.querySelector('.none') as Element)

        expect(assign).not.toHaveBeenCalled()
    })

    it('ignores non-primary buttons', () => {
        click(table.querySelector('.plain') as Element, { button: 2 })

        expect(assign).not.toHaveBeenCalled()
    })

    it('opens a new tab on ctrl-click', () => {
        const open = vi.fn()
        vi.stubGlobal('open', open)

        click(table.querySelector('.plain') as Element, { ctrlKey: true })

        expect(open).toHaveBeenCalledWith('/users/1', '_blank', 'noopener')
        expect(assign).not.toHaveBeenCalled()
    })
})
