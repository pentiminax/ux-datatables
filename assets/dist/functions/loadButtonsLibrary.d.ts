import type { StyleFramework } from '../types/styleFramework.js';
export declare function requiredExportPackages(layout: unknown): {
    jszip: boolean;
    pdfmake: boolean;
};
export declare function loadButtonsLibrary(DataTable: typeof import('datatables.net').default, framework: StyleFramework, layout: unknown): Promise<void>;
