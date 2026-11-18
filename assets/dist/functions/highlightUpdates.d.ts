import type { DataTableView } from '../types/dataTableView.js';
import type { ColumnConfig } from './apiPlatformAdapter.js';
export declare const HIGHLIGHT_CLASS = "dt-cell-updated";
export interface HighlightConfig {
    durationMs?: number;
    ignoreColumns?: string[];
    idField?: string;
}
interface HighlightTable {
    rows: () => {
        indexes: () => {
            toArray: () => number[];
        };
    };
    row: (selector: number) => {
        data: () => Record<string, unknown>;
    };
    cell: (rowIdx: number, colIdx: number) => {
        node: () => HTMLElement | null;
    };
}
export declare class UpdateHighlighter {
    private readonly table;
    private readonly onHighlight?;
    private readonly durationMs;
    private readonly ignored;
    private readonly columnKeys;
    private readonly timers;
    private snapshot;
    constructor(table: HighlightTable, config: HighlightConfig, columns: ColumnConfig[], onHighlight?: ((cells: HTMLElement[]) => void) | undefined);
    arm(): void;
    destroy(): void;
    diff(): void;
    private readRows;
    private rowIndexes;
    private flash;
}
export declare function isHighlightEnabled(payload: DataTableView): payload is DataTableView & {
    highlight: HighlightConfig;
};
export {};
