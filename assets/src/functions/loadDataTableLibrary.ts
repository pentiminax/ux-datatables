import type { StyleFramework } from '../types/styleFramework.js'

type Loader = () => Promise<any>

const loaders: Record<StyleFramework, Loader> = {
    dt: () => import('datatables.net-dt'),
    bs: () => import('datatables.net-bs'),
    bs4: () => import('datatables.net-bs4'),
    bs5: () => import('datatables.net-bs5'),
}

export async function loadDataTableLibrary(framework: StyleFramework): Promise<any> {
    return (await loaders[framework]()).default
}
