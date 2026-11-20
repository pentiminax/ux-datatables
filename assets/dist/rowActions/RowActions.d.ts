import type { DataTableView } from '../types/dataTableView.js';
import type { StyleFramework } from '../types/styleFramework.js';
export interface RowActionsTable {
    ajax?: {
        reload: (callback?: (() => void) | null, resetPaging?: boolean) => void;
    };
    row: (selector: any) => {
        child: ((content?: any) => {
            show: () => void;
            hide: () => void;
        }) & {
            isShown: () => boolean;
            show: () => void;
            hide: () => void;
        };
    };
}
export interface RowActionsContext {
    element: HTMLElement;
    framework: StyleFramework;
    getTable: () => RowActionsTable | null | undefined;
    dispatch: (name: string, detail: Record<string, unknown>) => Event;
}
export declare function installRowActions(payload: DataTableView, context: RowActionsContext): void;
