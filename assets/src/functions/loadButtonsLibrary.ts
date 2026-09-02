import type { StyleFramework } from '../types/styleFramework.js'

type Loader = () => Promise<any>

const frameworkLoaders: Record<StyleFramework, Loader> = {
    dt: () => import('datatables.net-buttons-dt'),
    bs: () => import('datatables.net-buttons-bs'),
    bs4: () => import('datatables.net-buttons-bs4'),
    bs5: () => import('datatables.net-buttons-bs5'),
    zf: () => import('datatables.net-buttons-zf'),
    jqui: () => import('datatables.net-buttons-jqui'),
    se: () => import('datatables.net-buttons-se'),
}

const frameworkStyleLoaders: Record<StyleFramework, Loader> = {
    dt: () => import('datatables.net-buttons-dt/css/buttons.dataTables.min.css'),
    bs: () => import('datatables.net-buttons-bs/css/buttons.bootstrap.min.css'),
    bs4: () => import('datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css'),
    bs5: () => import('datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css'),
    zf: () => import('datatables.net-buttons-zf/css/buttons.foundation.min.css'),
    jqui: () => import('datatables.net-buttons-jqui/css/buttons.jqueryui.min.css'),
    se: () => import('datatables.net-buttons-se/css/buttons.semanticui.min.css'),
}

export async function loadButtonsLibrary(
    DataTable: typeof import('datatables.net').default,
    framework: StyleFramework
): Promise<void> {
    const [{ default: JSZip }, { default: pdfMake }] = await Promise.all([
        import('jszip'),
        import('pdfmake'),
    ])

    await Promise.all([
        import('pdfmake/build/vfs_fonts.js'),
        import('datatables.net-buttons'),
        frameworkLoaders[framework](),
        frameworkStyleLoaders[framework](),
    ])

    DataTable.Buttons.jszip(JSZip)
    DataTable.Buttons.pdfMake(pdfMake)
}
