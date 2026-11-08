import type { StyleFramework } from '../types/styleFramework.js'
import { defaultExport, packageLoaders } from './packageLoaders.js'

/** JSZip and pdfmake stay optional: without them only the Excel and PDF buttons are unavailable. */
export async function loadButtonsLibrary(
    DataTable: typeof import('datatables.net').default,
    framework: StyleFramework
): Promise<void> {
    const [, jszip, pdfMake] = await Promise.all([
        packageLoaders.load('buttons', framework),
        packageLoaders.has('jszip') ? packageLoaders.load('jszip') : null,
        packageLoaders.has('pdfmake') ? packageLoaders.load('pdfmake') : null,
    ])

    if (jszip) {
        DataTable.Buttons.jszip(defaultExport(jszip))
    }

    if (pdfMake) {
        DataTable.Buttons.pdfMake(defaultExport(pdfMake))
    }
}
