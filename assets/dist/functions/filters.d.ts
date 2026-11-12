import type { StyleFramework } from '../types/styleFramework.js';
export type FilterType = 'text' | 'select' | 'ternary' | 'dateRange' | 'numberRange' | 'checkbox';
export interface FilterDefinition {
    name: string;
    type: FilterType;
    label?: string;
    placeholder?: string;
    options?: Record<string, string> | Array<[string, string]>;
    multiple?: boolean;
    trueLabel?: string;
    falseLabel?: string;
    minPlaceholder?: string;
    maxPlaceholder?: string;
}
export type RangeValue = {
    from?: string;
    to?: string;
    min?: string;
    max?: string;
};
export type FilterValue = string | string[] | RangeValue;
export interface FilterBarLabels {
    title?: string;
    reset?: string;
    apply?: string;
    all?: string;
}
export declare function hasFilters(payload: Record<string, any>): boolean;
export declare class FilterBar {
    private readonly framework;
    private readonly definitions;
    private readonly labels;
    private readonly controls;
    private readonly wrapper;
    private readonly popover;
    private readonly toggle;
    private readonly badge;
    private readonly headerResetButton;
    private applied;
    private reload;
    private popoverController;
    constructor(payload: Record<string, any>, framework: StyleFramework);
    collectValues(): Record<string, FilterValue>;
    private snapshot;
    restoreValues(values: unknown): void;
    private validateValueForDefinition;
    render(reload: () => void): HTMLElement;
    private buildHeader;
    private buildFooter;
    private applyFilters;
    resetFilters(): void;
    private updateBadge;
    private buildControl;
    private buildField;
    private buildText;
    private buildSelect;
    private buildTernary;
    private buildDateRange;
    private buildNumberRange;
    private buildCheckbox;
}
export declare function registerFilterFeature(DataTable: any): void;
export declare function installFilterBar(payload: Record<string, any>, DataTable: any, framework: StyleFramework): FilterBar | null;
