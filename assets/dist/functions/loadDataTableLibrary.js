const loaders = {
    dt: () => import('datatables.net-dt'),
    bs: () => import('datatables.net-bs'),
    bs4: () => import('datatables.net-bs4'),
    bs5: () => import('datatables.net-bs5'),
};
export async function loadDataTableLibrary(framework) {
    return (await loaders[framework]()).default;
}
//# sourceMappingURL=loadDataTableLibrary.js.map