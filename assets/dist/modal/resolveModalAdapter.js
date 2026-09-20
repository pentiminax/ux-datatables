import { modalAdapters } from './ModalAdapterRegistry.js';
export async function resolveModalAdapter(explicitKey, framework) {
    const key = explicitKey ?? framework;
    const factory = modalAdapters.get(key);
    if (!factory) {
        console.error(`[ux-datatables] No modal adapter registered for "${key}".`);
        return null;
    }
    const adapter = await factory();
    return isAdapterConstructor(adapter) ? new adapter() : adapter;
}
function isAdapterConstructor(adapter) {
    return typeof adapter === 'function';
}
//# sourceMappingURL=resolveModalAdapter.js.map