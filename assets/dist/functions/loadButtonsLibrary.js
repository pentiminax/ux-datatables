import { defaultExport, packageLoaders } from './packageLoaders.js';
export async function loadButtonsLibrary(DataTable, framework) {
    const [, jszip, pdfMake] = await Promise.all([
        packageLoaders.load('buttons', framework),
        packageLoaders.has('jszip') ? packageLoaders.load('jszip') : null,
        packageLoaders.has('pdfmake') ? packageLoaders.load('pdfmake') : null,
    ]);
    if (jszip) {
        DataTable.Buttons.jszip(defaultExport(jszip));
    }
    if (pdfMake) {
        DataTable.Buttons.pdfMake(defaultExport(pdfMake));
    }
}
//# sourceMappingURL=loadButtonsLibrary.js.map