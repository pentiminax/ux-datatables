export interface RowActionLabels {
    failed?: string;
    forbidden?: string;
}
export interface RowActionOutcome {
    ok: boolean;
    response?: Response;
}
export interface RunRowActionOptions<T extends RowActionOutcome> {
    element: HTMLElement;
    root: HTMLElement;
    actionType: string;
    id: string;
    labels: RowActionLabels;
    reportSuccess?: boolean;
    dispatch: (name: string, detail: Record<string, unknown>) => Event;
    run: () => Promise<T>;
}
export declare function runRowAction<T extends RowActionOutcome>({ element, root, actionType, id, labels, reportSuccess, dispatch, run, }: RunRowActionOptions<T>): Promise<T | null>;
