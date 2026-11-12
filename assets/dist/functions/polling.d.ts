export interface PollingTable {
    ajax?: {
        reload: (callback?: (() => void) | null, resetPaging?: boolean) => void;
    };
    on: (event: string, callback: (...args: any[]) => void) => unknown;
    off: (event: string, callback?: (...args: any[]) => void) => unknown;
}
export declare function isPollingEnabled(payload: Record<string, any>): boolean;
export declare function startPolling(table: PollingTable, intervalSeconds: number, container: HTMLElement | null): () => void;
interface EnsurePollingOptions {
    table: PollingTable | null;
    payload: Record<string, any>;
    element: HTMLElement;
    mercureEnabled: boolean;
}
export declare function ensurePolling(current: (() => void) | null, { table, payload, element, mercureEnabled }: EnsurePollingOptions): (() => void) | null;
export {};
