import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
    HIGHLIGHT_CLASS,
    isHighlightEnabled,
    UpdateHighlighter,
} from '../src/functions/highlightUpdates'

type Row = Record<string, unknown>

class FakeTable {
    private drawHandler: (() => void) | null = null
    readonly nodes = new Map<string, HTMLElement>()

    constructor(public rowsData: Row[]) {}

    on(event: string, callback: () => void): void {
        if (event === 'draw.dt') {
            this.drawHandler = callback
        }
    }

    off(event: string): void {
        if (event === 'draw.dt') {
            this.drawHandler = null
        }
    }

    rows() {
        return { indexes: () => ({ toArray: () => this.rowsData.map((_, index) => index) }) }
    }

    row(index: number) {
        return { data: () => this.rowsData[index] }
    }

    cell(rowIndex: number, columnIndex: number) {
        const key = `${rowIndex}:${columnIndex}`

        if (!this.nodes.has(key)) {
            this.nodes.set(key, document.createElement('td'))
        }

        return { node: () => this.nodes.get(key) ?? null }
    }

    /** Replaces the displayed rows and fires the draw the controller would have triggered. */
    redrawWith(rows: Row[]): void {
        this.rowsData = rows
        this.drawHandler?.()
    }

    flashedCells(): string[] {
        return [...this.nodes.entries()]
            .filter(([, node]) => node.classList.contains(HIGHLIGHT_CLASS))
            .map(([key]) => key)
    }
}

const COLUMNS = [{ data: 'email' }, { data: 'active' }, { data: 'lastLoginAt' }]

const ROWS: Row[] = [
    { DT_RowId: '1', email: 'a@example.com', active: true, lastLoginAt: '1 minute ago' },
    { DT_RowId: '2', email: 'b@example.com', active: false, lastLoginAt: '2 minutes ago' },
]

function createHighlighter(
    table: FakeTable,
    config = {},
    onHighlight?: (cells: HTMLElement[]) => void
) {
    return new UpdateHighlighter(table as never, config, COLUMNS as never, onHighlight)
}

describe('UpdateHighlighter', () => {
    let table: FakeTable

    beforeEach(() => {
        vi.useFakeTimers()
        table = new FakeTable(structuredClone(ROWS))
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('highlights only the cells whose value changed', () => {
        const highlighter = createHighlighter(table)
        highlighter.arm()

        table.redrawWith([
            { DT_RowId: '1', email: 'a@example.com', active: false, lastLoginAt: '1 minute ago' },
            { DT_RowId: '2', email: 'b@example.com', active: false, lastLoginAt: '2 minutes ago' },
        ])

        expect(table.flashedCells()).toEqual(['0:1'])
    })

    it('ignores a draw it was not armed for', () => {
        createHighlighter(table)

        table.redrawWith([
            { DT_RowId: '1', email: 'a@example.com', active: false, lastLoginAt: '1 minute ago' },
            ...structuredClone(ROWS).slice(1),
        ])

        expect(table.flashedCells()).toEqual([])
    })

    it('does not highlight rows that only moved', () => {
        const highlighter = createHighlighter(table)
        highlighter.arm()

        table.redrawWith(structuredClone(ROWS).reverse())

        expect(table.flashedCells()).toEqual([])
    })

    it('skips ignored columns', () => {
        const highlighter = createHighlighter(table, { ignoreColumns: ['lastLoginAt'] })
        highlighter.arm()

        table.redrawWith([
            { DT_RowId: '1', email: 'a@example.com', active: true, lastLoginAt: '3 minutes ago' },
            ...structuredClone(ROWS).slice(1),
        ])

        expect(table.flashedCells()).toEqual([])
    })

    it('skips rows that were not displayed before the refresh', () => {
        const highlighter = createHighlighter(table)
        highlighter.arm()

        table.redrawWith([
            { DT_RowId: '9', email: 'new@example.com', active: true, lastLoginAt: 'Never' },
        ])

        expect(table.flashedCells()).toEqual([])
    })

    it('removes the class once the configured duration elapsed', () => {
        const highlighter = createHighlighter(table, { durationMs: 500 })
        highlighter.arm()

        table.redrawWith([
            { DT_RowId: '1', email: 'a@example.com', active: false, lastLoginAt: '1 minute ago' },
            ...structuredClone(ROWS).slice(1),
        ])

        const cell = table.cell(0, 1).node() as HTMLElement
        expect(cell.style.getPropertyValue('--dt-highlight-duration')).toBe('500ms')

        vi.advanceTimersByTime(500)

        expect(cell.classList.contains(HIGHLIGHT_CLASS)).toBe(false)
    })

    it('reports the highlighted cells', () => {
        const onHighlight = vi.fn()
        const highlighter = createHighlighter(table, {}, onHighlight)
        highlighter.arm()

        table.redrawWith([
            { DT_RowId: '1', email: 'z@example.com', active: false, lastLoginAt: '1 minute ago' },
            ...structuredClone(ROWS).slice(1),
        ])

        expect(onHighlight).toHaveBeenCalledTimes(1)
        expect(onHighlight.mock.calls[0][0]).toHaveLength(2)
    })

    it('stops diffing once destroyed', () => {
        const highlighter = createHighlighter(table)
        highlighter.arm()
        highlighter.destroy()

        table.redrawWith([
            { DT_RowId: '1', email: 'a@example.com', active: false, lastLoginAt: '1 minute ago' },
            ...structuredClone(ROWS).slice(1),
        ])

        expect(table.flashedCells()).toEqual([])
    })
})

describe('isHighlightEnabled', () => {
    it.each([
        [{ highlight: { durationMs: 1200 } }, true],
        [{ highlight: null }, false],
        [{}, false],
    ])('reads the payload flag (%o)', (payload, expected) => {
        expect(isHighlightEnabled(payload)).toBe(expected)
    })
})
