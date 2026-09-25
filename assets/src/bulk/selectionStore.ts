/**
 * Keeps track of what the user selected.
 *
 * Server-side paging destroys and rebuilds every row on each draw, so DataTables' own selection
 * does not survive a page change. The store holds `DT_RowId` values instead and re-selects the
 * matching rows after every draw.
 */
export interface SelectionSnapshot {
    ids: string[]
    allMatching: boolean
    deselectedIds: string[]
    count: number
    pageCount: number
    totalCount: number
}

type Listener = (snapshot: SelectionSnapshot) => void

export class SelectionStore {
    private readonly ids = new Set<string>()
    private readonly deselectedIds = new Set<string>()
    private allMatching = false
    private listener: Listener = () => {}
    private restoring = false

    constructor(private readonly api: any) {}

    attach(listener: Listener): void {
        this.listener = listener

        this.api.on('select.dtBulk', (_e: unknown, _dt: unknown, type: string, indexes: number[]) =>
            this.onSelectionChange(type, indexes, true)
        )
        this.api.on(
            'deselect.dtBulk',
            (_e: unknown, _dt: unknown, type: string, indexes: number[]) =>
                this.onSelectionChange(type, indexes, false)
        )
        this.api.on('draw.dtBulk', () => this.restore())

        this.restore()
    }

    detach(): void {
        this.api.off('select.dtBulk')
        this.api.off('deselect.dtBulk')
        this.api.off('draw.dtBulk')
    }

    selectAllMatching(): void {
        if (!this.isServerSide()) {
            this.ids.clear()

            for (const id of this.filteredIds()) {
                this.ids.add(id)
            }

            this.allMatching = false
            this.deselectedIds.clear()
            this.restore()

            return
        }

        this.allMatching = true
        this.deselectedIds.clear()
        this.restore()
    }

    clear(): void {
        this.ids.clear()
        this.deselectedIds.clear()
        this.allMatching = false
        this.api.rows({ selected: true }).deselect()
        this.emit()
    }

    snapshot(): SelectionSnapshot {
        const total = this.totalCount()

        return {
            ids: [...this.ids],
            allMatching: this.allMatching,
            deselectedIds: [...this.deselectedIds],
            count: this.allMatching ? Math.max(total - this.deselectedIds.size, 0) : this.ids.size,
            pageCount: this.pageIds().length,
            totalCount: total,
        }
    }

    private onSelectionChange(type: string, indexes: number[], selected: boolean): void {
        if (type !== 'row' || this.restoring) {
            return
        }

        for (const id of this.rowIds(indexes)) {
            if (selected) {
                this.ids.add(id)
                this.deselectedIds.delete(id)

                continue
            }

            this.ids.delete(id)

            if (this.allMatching) {
                this.deselectedIds.add(id)
            }
        }

        this.emit()
    }

    /**
     * Re-apply the stored selection to the rows the new draw produced.
     *
     * `restoring` keeps the select events this triggers from feeding back into the store, which
     * would otherwise re-add rows the user deselected while "all matching" is on.
     */
    private restore(): void {
        this.restoring = true

        try {
            for (const index of this.api.rows({ page: 'current' }).indexes().toArray()) {
                const row = this.api.row(index)
                const id = String(row.id() ?? '')

                if (id === '' || id === 'undefined') {
                    continue
                }

                const selected = this.isSelected(id)

                if (selected === row.selected()) {
                    continue
                }

                if (selected) {
                    row.select()
                } else {
                    row.deselect()
                }
            }
        } finally {
            this.restoring = false
        }

        this.emit()
    }

    private isSelected(id: string): boolean {
        return this.allMatching ? !this.deselectedIds.has(id) : this.ids.has(id)
    }

    private rowIds(indexes: number[]): string[] {
        return this.api
            .rows(indexes)
            .ids()
            .toArray()
            .map((id: unknown) => String(id))
            .filter((id: string) => id !== '' && id !== 'undefined')
    }

    private pageIds(): string[] {
        return this.rowIdsFor({ page: 'current' })
    }

    private filteredIds(): string[] {
        return this.rowIdsFor({ search: 'applied' })
    }

    private rowIdsFor(selector: Record<string, string>): string[] {
        return this.api
            .rows(selector)
            .ids()
            .toArray()
            .map((id: unknown) => String(id))
            .filter((id: string) => id !== '' && id !== 'undefined')
    }

    private totalCount(): number {
        const info = this.api.page?.info?.()

        return typeof info?.recordsDisplay === 'number' ? info.recordsDisplay : this.ids.size
    }

    private isServerSide(): boolean {
        return this.api.page?.info?.()?.serverSide === true
    }

    private emit(): void {
        this.listener(this.snapshot())
    }
}
