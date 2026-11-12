import type { StyleFramework } from '../types/styleFramework.js';
export interface BulkActionDefinition {
    name: string;
    label: string;
    className?: string;
    icon?: string;
    lucideIcon?: string;
    confirm?: string;
    confirmButton?: string;
    successMessage?: string;
    deselectAfterCompletion?: boolean;
}
export interface BulkActionLabels {
    trigger?: string;
    selected?: string;
    selectAllMatching?: string;
    allMatchingSelected?: string;
    clear?: string;
    confirm?: string;
    cancel?: string;
    processed?: string;
    skipped?: string;
    failed?: string;
}
export interface BulkActionsConfig {
    actions: BulkActionDefinition[];
    selectCurrentPageOnly?: boolean;
    idField?: string;
    url?: string | null;
    labels?: BulkActionLabels;
    position?: string;
}
export declare function hasBulkActions(payload: Record<string, any>): boolean;
export declare function getBulkActionsConfig(payload: Record<string, any>): BulkActionsConfig;
export declare class BulkActionBar {
    private readonly framework;
    private readonly dispatch;
    private readonly currentParams;
    private readonly config;
    private readonly labels;
    private readonly wrapper;
    private readonly trigger;
    private readonly menu;
    private readonly summary;
    private readonly summaryCount;
    private readonly selectAllButton;
    private readonly clearButton;
    private readonly dataTable;
    private readonly csrfToken?;
    private readonly mutationsEnabled;
    private readonly modalAdapterKey;
    private popover;
    private store;
    private api;
    private running;
    private resultMessage;
    constructor(payload: Record<string, any>, framework: StyleFramework, dispatch?: (name: string, detail: Record<string, unknown>) => void, currentParams?: () => Record<string, unknown>);
    render(api: any): HTMLElement;
    private mountSummary;
    private update;
    private setSummaryEmpty;
    private countLabel;
    private canRun;
    private createMenuItem;
    private execute;
    private showResult;
    private failureMessage;
    private summarize;
    private reload;
    private createButton;
    private createLink;
    private buttonClass;
}
export declare function registerBulkActionsFeature(DataTable: any): void;
export declare function installBulkActionBar(payload: Record<string, any>, DataTable: any, framework: StyleFramework, dispatch: (name: string, detail: Record<string, unknown>) => void, currentParams: () => Record<string, unknown>): BulkActionBar | null;
