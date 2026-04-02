import type { StyleFramework } from '../types/styleFramework.js'

type Loader = () => Promise<any>

const loaders: Record<StyleFramework, Loader> = {
    dt: () => import('datatables.net-dt'),
    bs: () => import('datatables.net-bs'),
    bs4: () => import('datatables.net-bs4'),
    bs5: () => import('datatables.net-bs5'),
    bm: () => import('datatables.net-bm'),
    zf: () => import('datatables.net-zf'),
    jqui: () => import('datatables.net-jqui'),
    se: () => import('datatables.net-se'),
}

export async function loadDataTableLibrary(framework: StyleFramework): Promise<any> {
    return (await loaders[framework]()).default
}
