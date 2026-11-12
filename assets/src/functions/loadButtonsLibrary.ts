import type { StyleFramework } from '../types/styleFramework.js'
import { defaultExport, packageLoaders } from './packageLoaders.js'

const BUTTON_LIST_KEYS = new Set(['buttons', 'prefixButtons', 'postfixButtons', 'split'])

export function requiredExportPackages(layout: unknown): { jszip: boolean; pdfmake: boolean } {
    const required = { jszip: false, pdfmake: false }

    const markName = (name: string) => {
        required.jszip ||= name.startsWith('excel')
        required.pdfmake ||= name.startsWith('pdf')
    }

    const walk = (node: unknown, isButtonList: boolean) => {
        if (typeof node === 'string') {
            if (isButtonList) {
                markName(node)
            } else if (node === 'buttons') {
                // DataTables' default button set includes Excel and PDF
                required.jszip = required.pdfmake = true
            }

            return
        }

        if (Array.isArray(node)) {
            for (const item of node) {
                walk(item, isButtonList)
            }

            return
        }

        if (typeof node !== 'object' || node === null) {
            return
        }

        for (const [key, value] of Object.entries(node)) {
            if (key === 'extend' && typeof value === 'string') {
                markName(value)
            } else {
                walk(value, BUTTON_LIST_KEYS.has(key))
            }
        }
    }

    walk(layout, false)

    return required
}

async function loadExportPackage(name: 'jszip' | 'pdfmake', required: boolean): Promise<unknown> {
    if (!required) {
        return null
    }

    const buttons = name === 'jszip' ? 'Excel' : 'PDF'

    if (!packageLoaders.has(name)) {
        console.warn(
            `The "${name}" package is not registered, so the ${buttons} buttons are unavailable. Install it and register it with packageLoaders.register('${name}', loader).`
        )

        return null
    }

    try {
        return await packageLoaders.load(name)
    } catch (error) {
        console.warn(
            `The "${name}" package failed to load, so the ${buttons} buttons are unavailable.`,
            error
        )

        return null
    }
}

/** JSZip and pdfmake stay optional: without them only the Excel and PDF buttons are unavailable. */
export async function loadButtonsLibrary(
    DataTable: typeof import('datatables.net').default,
    framework: StyleFramework,
    layout: unknown
): Promise<void> {
    const required = requiredExportPackages(layout)
    const [, jszip, pdfMake] = await Promise.all([
        packageLoaders.load('buttons', framework),
        loadExportPackage('jszip', required.jszip),
        loadExportPackage('pdfmake', required.pdfmake),
    ])

    if (jszip) {
        DataTable.Buttons.jszip(defaultExport(jszip))
    }

    if (pdfMake) {
        DataTable.Buttons.pdfMake(defaultExport(pdfMake))
    }
}
