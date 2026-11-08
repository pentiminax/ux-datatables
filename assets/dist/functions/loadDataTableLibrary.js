import { defaultExport, packageLoaders } from './packageLoaders.js';
export async function loadDataTableLibrary(framework) {
    return defaultExport(await packageLoaders.load('dataTables', framework));
}
//# sourceMappingURL=loadDataTableLibrary.js.map