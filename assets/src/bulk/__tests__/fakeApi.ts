type Handler = (...args: any[]) => void

export interface FakeRow {
    id: string
    selected: boolean
}

/**
 * The slice of the DataTables API the selection store and the bar actually use.
 */
export class FakeApi {
    readonly reloaded: number[] = []
    private handlers = new Map<string, Handler[]>()

    constructor(
        private rowsOnPage: FakeRow[],
        private recordsDisplay = rowsOnPage.length,
        private serverSide = true
    ) {}

    page = {
        info: () => ({ recordsDisplay: this.recordsDisplay, serverSide: this.serverSide }),
    }

    ajax = {
        params: () => ({ draw: 1, search: { value: 'Beta' } }),
        reload: () => this.reloaded.push(1),
    }

    on(event: string, handler: Handler): void {
        const [name] = event.split('.')
        this.handlers.set(name, [...(this.handlers.get(name) ?? []), handler])
    }

    off(event: string): void {
        this.handlers.delete(event.split('.')[0])
    }

    rows(selector?: any): any {
        const rows = Array.isArray(selector)
            ? selector.map((index: number) => this.rowsOnPage[index])
            : selector?.selected === true
              ? this.rowsOnPage.filter((row) => row.selected)
              : this.rowsOnPage

        return {
            indexes: () => ({ toArray: () => rows.map((row) => this.rowsOnPage.indexOf(row)) }),
            ids: () => ({ toArray: () => rows.map((row) => row.id) }),
            deselect: () => {
                for (const row of rows) {
                    row.selected = false
                }
            },
        }
    }

    row(index: number): any {
        const row = this.rowsOnPage[index]

        return {
            id: () => row.id,
            selected: () => row.selected,
            select: () => {
                row.selected = true
            },
            deselect: () => {
                row.selected = false
            },
        }
    }

    /** Simulate what DataTables emits when the user checks or unchecks rows. */
    emitSelection(event: 'select' | 'deselect', indexes: number[]): void {
        for (const index of indexes) {
            this.rowsOnPage[index].selected = event === 'select'
        }

        for (const handler of this.handlers.get(event) ?? []) {
            handler(null, null, 'row', indexes)
        }
    }

    /** Simulate a server-side page change: new rows, then a draw. */
    drawPage(rows: FakeRow[]): void {
        this.rowsOnPage = rows

        for (const handler of this.handlers.get('draw') ?? []) {
            handler()
        }
    }
}
