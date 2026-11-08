import type { StyleFramework } from '../types/styleFramework.js'
import { packageLoaders } from './packageLoaders.js'

const EXTENSION_NAMES = [
    'colReorder',
    'columnControl',
    'fixedColumns',
    'fixedHeader',
    'keyTable',
    'responsive',
    'rowGroup',
    'scroller',
    'searchBuilder',
    'select',
] as const

export type ExtensionName = (typeof EXTENSION_NAMES)[number]

function isExtensionName(name: string): name is ExtensionName {
    return (EXTENSION_NAMES as readonly string[]).includes(name)
}

export class ExtensionRegistry {
    static async load(name: string, framework: StyleFramework): Promise<void> {
        if (!isExtensionName(name)) {
            throw new Error(`Unknown extension: "${name}"`)
        }

        await packageLoaders.load(name, framework)
    }
}
