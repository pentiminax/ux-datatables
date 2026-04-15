import type { StyleFramework } from '../types/styleFramework.js'
import { modalAdapters } from './ModalAdapterRegistry.js'
import type { ModalAdapter, ModalAdapterConstructor } from './ModalAdapter.js'

export async function resolveModalAdapter(
    explicitKey: string | null,
    framework: StyleFramework
): Promise<ModalAdapter | null> {
    const key = explicitKey ?? framework
    const factory = modalAdapters.get(key)

    if (!factory) {
        console.error(`[ux-datatables] No modal adapter registered for "${key}".`)

        return null
    }

    const adapter = await factory()

    return isAdapterConstructor(adapter) ? new adapter() : adapter
}

function isAdapterConstructor(
    adapter: ModalAdapter | ModalAdapterConstructor
): adapter is ModalAdapterConstructor {
    return typeof adapter === 'function'
}
