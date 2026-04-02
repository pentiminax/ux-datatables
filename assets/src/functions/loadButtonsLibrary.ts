import type { StyleFramework } from '../types/styleFramework.js'

type Loader = () => Promise<any>

const frameworkLoaders: Record<StyleFramework, Loader> = {
    dt: () => import('datatables.net-buttons-dt'),
    bs: () => import('datatables.net-buttons-bs'),
    bs4: () => import('datatables.net-buttons-bs4'),
    bs5: () => import('datatables.net-buttons-bs5'),
    bm: () => import('datatables.net-buttons-bm'),
    zf: () => import('datatables.net-buttons-zf'),
    jqui: () => import('datatables.net-buttons-jqui'),
    se: () => import('datatables.net-buttons-se'),
}

export async function loadButtonsLibrary(
    DataTable: typeof import('datatables.net/types/types').default,
    framework: StyleFramework
): Promise<void> {
    const [{ default: JSZip }, { default: pdfMake }] = await Promise.all([
        import('jszip'),
        import('pdfmake'),
    ])

    await Promise.all([
        import('pdfmake/build/vfs_fonts'),
        import('datatables.net-buttons'),
        import('datatables.net-buttons/js/buttons.colVis'),
        import('datatables.net-buttons/js/buttons.html5'),
        import('datatables.net-buttons/js/buttons.print'),
        frameworkLoaders[framework](),
    ])

    DataTable.Buttons.jszip(JSZip)
    DataTable.Buttons.pdfMake(pdfMake)
}
