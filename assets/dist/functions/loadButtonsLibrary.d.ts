import type { StyleFramework } from '../types/styleFramework.js';
export declare function requiredExportPackages(layout: unknown): {
    jszip: boolean;
    pdfmake: boolean;
};
export declare function loadButtonsLibrary(DataTable: {
    Buttons: {
        jszip: (jszip: unknown) => void;
        pdfMake: (pdfMake: unknown) => void;
    };
}, framework: StyleFramework, layout: unknown): Promise<void>;
