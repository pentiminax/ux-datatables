import { Controller } from '@hotwired/stimulus';
import type { DataTableView } from './types/dataTableView.js';
export default class extends Controller {
    readonly viewValue: DataTableView;
    static readonly values: {
        view: ObjectConstructor;
    };
    private table;
    private isDataTableInitialized;
    private eventSource;
    private stopPolling;
    private highlighter;
    private framework;
    private popstateHandler;
    private readonly onTurboBeforeCache;
    connect(): Promise<void>;
    disconnect(): void;
    private adoptLiveTable;
    private bindPopstate;
    private applyUrlStateToTable;
    private resetRestoredMarkup;
    private findGeneratedWrapper;
    private isGeneratedMarkup;
    private loadExtensions;
    private configureColumns;
    private startMercure;
    private initMercure;
    private initPolling;
    private initHighlighter;
    private hasButtonsInLayout;
    private dispatchEvent;
    private isApiPlatformEnabled;
    private isMercureEnabled;
    private areMutationsEnabled;
    private getMercureTopics;
}
