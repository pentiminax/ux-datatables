import { packageLoaders } from './packageLoaders.js';
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
];
function isExtensionName(name) {
    return EXTENSION_NAMES.includes(name);
}
export class ExtensionRegistry {
    static async load(name, framework) {
        if (!isExtensionName(name)) {
            throw new Error(`Unknown extension: "${name}"`);
        }
        await packageLoaders.load(name, framework);
    }
}
//# sourceMappingURL=extensionRegistry.js.map