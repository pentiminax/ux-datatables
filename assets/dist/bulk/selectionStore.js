export class SelectionStore {
    constructor(api) {
        this.api = api;
        this.ids = new Set();
        this.deselectedIds = new Set();
        this.allMatching = false;
        this.listener = () => { };
        this.restoring = false;
    }
    attach(listener) {
        this.listener = listener;
        this.api.on('select.dtBulk', (_e, _dt, type, indexes) => this.onSelectionChange(type, indexes, true));
        this.api.on('deselect.dtBulk', (_e, _dt, type, indexes) => this.onSelectionChange(type, indexes, false));
        this.api.on('draw.dtBulk', () => this.restore());
        this.restore();
    }
    detach() {
        this.api.off('select.dtBulk');
        this.api.off('deselect.dtBulk');
        this.api.off('draw.dtBulk');
    }
    selectAllMatching() {
        if (!this.isServerSide()) {
            this.ids.clear();
            for (const id of this.filteredIds()) {
                this.ids.add(id);
            }
            this.allMatching = false;
            this.deselectedIds.clear();
            this.restore();
            return;
        }
        this.allMatching = true;
        this.deselectedIds.clear();
        this.restore();
    }
    clear() {
        this.ids.clear();
        this.deselectedIds.clear();
        this.allMatching = false;
        this.api.rows({ selected: true }).deselect();
        this.emit();
    }
    snapshot() {
        const total = this.totalCount();
        return {
            ids: [...this.ids],
            allMatching: this.allMatching,
            deselectedIds: [...this.deselectedIds],
            count: this.allMatching ? Math.max(total - this.deselectedIds.size, 0) : this.ids.size,
            pageCount: this.pageIds().length,
            totalCount: total,
        };
    }
    onSelectionChange(type, indexes, selected) {
        if (type !== 'row' || this.restoring) {
            return;
        }
        for (const id of this.rowIds(indexes)) {
            if (selected) {
                this.ids.add(id);
                this.deselectedIds.delete(id);
                continue;
            }
            this.ids.delete(id);
            if (this.allMatching) {
                this.deselectedIds.add(id);
            }
        }
        this.emit();
    }
    restore() {
        this.restoring = true;
        try {
            for (const index of this.api.rows({ page: 'current' }).indexes().toArray()) {
                const row = this.api.row(index);
                const id = String(row.id() ?? '');
                if (id === '' || id === 'undefined') {
                    continue;
                }
                const selected = this.isSelected(id);
                if (selected === row.selected()) {
                    continue;
                }
                if (selected) {
                    row.select();
                }
                else {
                    row.deselect();
                }
            }
        }
        finally {
            this.restoring = false;
        }
        this.emit();
    }
    isSelected(id) {
        return this.allMatching ? !this.deselectedIds.has(id) : this.ids.has(id);
    }
    rowIds(indexes) {
        return this.api
            .rows(indexes)
            .ids()
            .toArray()
            .map((id) => String(id))
            .filter((id) => id !== '' && id !== 'undefined');
    }
    pageIds() {
        return this.rowIdsFor({ page: 'current' });
    }
    filteredIds() {
        return this.rowIdsFor({ search: 'applied' });
    }
    rowIdsFor(selector) {
        return this.api
            .rows(selector)
            .ids()
            .toArray()
            .map((id) => String(id))
            .filter((id) => id !== '' && id !== 'undefined');
    }
    totalCount() {
        const info = this.api.page?.info?.();
        return typeof info?.recordsDisplay === 'number' ? info.recordsDisplay : this.ids.size;
    }
    isServerSide() {
        return this.api.page?.info?.()?.serverSide === true;
    }
    emit() {
        this.listener(this.snapshot());
    }
}
//# sourceMappingURL=selectionStore.js.map