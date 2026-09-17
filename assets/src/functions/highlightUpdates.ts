import type { ColumnConfig } from './apiPlatformAdapter.js'

export const HIGHLIGHT_CLASS = 'dt-cell-updated'

const ROW_ID_KEY = 'DT_RowId'
const DEFAULT_DURATION_MS = 1200

export interface HighlightConfig {
    durationMs?: number
    ignoreColumns?: string[]
}

interface HighlightTable {
    rows: () => { indexes: () => { toArray: () => number[] } }
    row: (selector: number) => { data: () => Record<string, unknown> }
    cell: (rowIdx: number, colIdx: number) => { node: () => HTMLElement | null }
}

/**
 * Emphasizes the cells whose value changed across a refresh the table did not ask for.
 *
 * Diffing happens on row data rather than on rendered markup: a redraw regenerates cell HTML even
 * where nothing changed, so comparing markup would light up the whole table. Rows are matched by
 * their server-provided id, so a row that only moved to another position is not reported as
 * updated. Nothing is compared unless {@see UpdateHighlighter.arm} ran first and the refresh it
 * armed completed, which keeps user actions - sorting, searching, paging - out of the diff.
 */
export class UpdateHighlighter {
    private readonly durationMs: number
    private readonly ignored: Set<string>
    private readonly columnKeys: (string | null)[]
    private readonly timers = new Map<HTMLElement, ReturnType<typeof setTimeout>>()

    private snapshot: Map<string, Record<string, unknown>> | null = null

    constructor(
        private readonly table: HighlightTable,
        config: HighlightConfig,
        columns: ColumnConfig[],
        private readonly onHighlight?: (cells: HTMLElement[]) => void
    ) {
        this.durationMs = config.durationMs ?? DEFAULT_DURATION_MS
        this.ignored = new Set(config.ignoreColumns ?? [])
        this.columnKeys = columns.map((column) => column.data ?? column.name ?? null)
    }

    /**
     * Records the values currently displayed, so the refresh about to run can be compared against
     * them once {@see UpdateHighlighter.diff} reports it completed.
     */
    arm(): void {
        this.snapshot = this.readRows()
    }

    destroy(): void {
        for (const timer of this.timers.values()) {
            clearTimeout(timer)
        }

        this.timers.clear()
        this.snapshot = null
    }

    /**
     * Compares the redrawn rows against the armed snapshot. Called by the refresh that armed the
     * snapshot once its draw completed, so a sort, a search or a paging draw racing the refresh
     * neither consumes nor invalidates it.
     */
    diff(): void {
        const previous = this.snapshot

        if (previous === null) {
            return
        }

        this.snapshot = null

        const highlighted: HTMLElement[] = []

        for (const rowIndex of this.rowIndexes()) {
            const data = this.table.row(rowIndex).data()
            const rowId = readRowId(data)

            if (rowId === null) {
                continue
            }

            const before = previous.get(rowId)

            if (before === undefined) {
                continue
            }

            this.columnKeys.forEach((key, columnIndex) => {
                if (key === null || this.ignored.has(key)) {
                    return
                }

                if (!valuesDiffer(before[key], data[key])) {
                    return
                }

                const node = this.table.cell(rowIndex, columnIndex).node()

                if (node !== null) {
                    this.flash(node)
                    highlighted.push(node)
                }
            })
        }

        if (highlighted.length > 0) {
            this.onHighlight?.(highlighted)
        }
    }

    private readRows(): Map<string, Record<string, unknown>> {
        const rows = new Map<string, Record<string, unknown>>()

        for (const rowIndex of this.rowIndexes()) {
            const data = this.table.row(rowIndex).data()
            const rowId = readRowId(data)

            if (rowId !== null) {
                rows.set(rowId, data)
            }
        }

        return rows
    }

    private rowIndexes(): number[] {
        return this.table.rows().indexes().toArray()
    }

    /**
     * A draw hands back freshly created cell nodes, so the animation always starts from scratch and
     * needs no reflow trick. The timer only matters when no further draw happens before it fires.
     */
    private flash(node: HTMLElement): void {
        const running = this.timers.get(node)

        if (running !== undefined) {
            clearTimeout(running)
        }

        node.style.setProperty('--dt-highlight-duration', `${this.durationMs}ms`)
        node.classList.add(HIGHLIGHT_CLASS)

        this.timers.set(
            node,
            setTimeout(() => {
                node.classList.remove(HIGHLIGHT_CLASS)
                node.style.removeProperty('--dt-highlight-duration')
                this.timers.delete(node)
            }, this.durationMs)
        )
    }
}

export function isHighlightEnabled(payload: Record<string, unknown>): boolean {
    return typeof payload?.highlight === 'object' && payload.highlight !== null
}

function readRowId(data: Record<string, unknown>): string | null {
    const rowId = data?.[ROW_ID_KEY]

    if (typeof rowId === 'string' && rowId !== '') {
        return rowId
    }

    return typeof rowId === 'number' ? String(rowId) : null
}

/**
 * Row values arrive as JSON, so structural comparison is enough and cannot hit a cycle.
 */
function valuesDiffer(before: unknown, after: unknown): boolean {
    if (before === after) {
        return false
    }

    if (
        before === null ||
        after === null ||
        typeof before !== 'object' ||
        typeof after !== 'object'
    ) {
        return true
    }

    return JSON.stringify(before) !== JSON.stringify(after)
}
