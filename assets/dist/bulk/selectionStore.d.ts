export interface SelectionSnapshot {
    ids: string[];
    allMatching: boolean;
    deselectedIds: string[];
    count: number;
    pageCount: number;
    totalCount: number;
}
type Listener = (snapshot: SelectionSnapshot, changedByUser: boolean) => void;
export declare class SelectionStore {
    private readonly api;
    private readonly ids;
    private readonly deselectedIds;
    private allMatching;
    private listener;
    private restoring;
    constructor(api: any);
    attach(listener: Listener): void;
    detach(): void;
    selectAllMatching(): void;
    clear(): void;
    snapshot(): SelectionSnapshot;
    private onSelectionChange;
    private restore;
    private isSelected;
    private rowIds;
    private pageIds;
    private filteredIds;
    private rowIdsFor;
    private totalCount;
    private isServerSide;
    private emit;
}
export {};
