import { resolveColumnDataKey } from './apiPlatformAdapter.js';
export const HIGHLIGHT_CLASS = 'dt-cell-updated';
const ROW_ID_KEY = 'DT_RowId';
const DEFAULT_DURATION_MS = 1200;
export class UpdateHighlighter {
    constructor(table, config, columns, onHighlight) {
        this.table = table;
        this.onHighlight = onHighlight;
        this.timers = new Map();
        this.snapshot = null;
        this.onDraw = () => {
            const previous = this.snapshot;
            if (previous === null) {
                return;
            }
            this.snapshot = null;
            const highlighted = [];
            for (const rowIndex of this.rowIndexes()) {
                const data = this.table.row(rowIndex).data();
                const rowId = readRowId(data);
                if (rowId === null) {
                    continue;
                }
                const before = previous.get(rowId);
                if (before === undefined) {
                    continue;
                }
                this.columnKeys.forEach((key, columnIndex) => {
                    if (key === null || this.ignored.has(key)) {
                        return;
                    }
                    if (!valuesDiffer(before[key], data[key])) {
                        return;
                    }
                    const node = this.table.cell(rowIndex, columnIndex).node();
                    if (node !== null) {
                        this.flash(node);
                        highlighted.push(node);
                    }
                });
            }
            if (highlighted.length > 0) {
                this.onHighlight?.(highlighted);
            }
        };
        this.durationMs = config.durationMs ?? DEFAULT_DURATION_MS;
        this.ignored = new Set(config.ignoreColumns ?? []);
        this.columnKeys = columns.map((column) => resolveColumnDataKey(column) ?? null);
        this.table.on('draw.dt', this.onDraw);
    }
    arm() {
        this.snapshot = this.readRows();
    }
    destroy() {
        this.table.off('draw.dt', this.onDraw);
        for (const timer of this.timers.values()) {
            clearTimeout(timer);
        }
        this.timers.clear();
        this.snapshot = null;
    }
    readRows() {
        const rows = new Map();
        for (const rowIndex of this.rowIndexes()) {
            const data = this.table.row(rowIndex).data();
            const rowId = readRowId(data);
            if (rowId !== null) {
                rows.set(rowId, data);
            }
        }
        return rows;
    }
    rowIndexes() {
        return this.table.rows().indexes().toArray();
    }
    flash(node) {
        const running = this.timers.get(node);
        if (running !== undefined) {
            clearTimeout(running);
        }
        node.style.setProperty('--dt-highlight-duration', `${this.durationMs}ms`);
        node.classList.add(HIGHLIGHT_CLASS);
        this.timers.set(node, setTimeout(() => {
            node.classList.remove(HIGHLIGHT_CLASS);
            node.style.removeProperty('--dt-highlight-duration');
            this.timers.delete(node);
        }, this.durationMs));
    }
}
export function isHighlightEnabled(payload) {
    return typeof payload?.highlight === 'object' && payload.highlight !== null;
}
function readRowId(data) {
    const rowId = data?.[ROW_ID_KEY];
    if (typeof rowId === 'string' && rowId !== '') {
        return rowId;
    }
    return typeof rowId === 'number' ? String(rowId) : null;
}
function valuesDiffer(before, after) {
    if (before === after) {
        return false;
    }
    if (before === null ||
        after === null ||
        typeof before !== 'object' ||
        typeof after !== 'object') {
        return true;
    }
    return JSON.stringify(before) !== JSON.stringify(after);
}
//# sourceMappingURL=highlightUpdates.js.map