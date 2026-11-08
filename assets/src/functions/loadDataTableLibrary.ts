import type { StyleFramework } from '../types/styleFramework.js'
import { defaultExport, packageLoaders } from './packageLoaders.js'

export async function loadDataTableLibrary(framework: StyleFramework): Promise<any> {
    return defaultExport(await packageLoaders.load('dataTables', framework))
}
