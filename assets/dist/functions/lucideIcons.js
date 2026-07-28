let lucide = null;
let iconsByKebab = null;
export async function loadLucideIcons() {
    if (lucide !== null) {
        return;
    }
    lucide = (await import('lucide'));
    iconsByKebab = new Map();
    for (const [pascal, node] of Object.entries(lucide.icons)) {
        iconsByKebab.set(pascalToKebab(pascal), node);
    }
}
export function renderLucideIcon(iconName, attributes = {}) {
    const normalizedName = iconName.trim();
    if (lucide === null || iconsByKebab === null || normalizedName.length === 0) {
        return null;
    }
    const iconNode = iconsByKebab.get(normalizedName);
    if (iconNode === undefined) {
        return null;
    }
    return lucide.createElement(iconNode, attributes).outerHTML;
}
export function hasLucideIcons(columns) {
    if (!Array.isArray(columns)) {
        return false;
    }
    return columns.some((column) => {
        if (!isRecord(column)) {
            return false;
        }
        if (isRecord(column.customOptions) && column.customOptions.isIcon === true) {
            return true;
        }
        if (!Array.isArray(column.actions)) {
            return false;
        }
        return column.actions.some((action) => isRecord(action) &&
            typeof action.lucideIcon === 'string' &&
            action.lucideIcon.trim().length > 0);
    });
}
function pascalToKebab(name) {
    return name
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase();
}
function isRecord(value) {
    return typeof value === 'object' && value !== null;
}
//# sourceMappingURL=lucideIcons.js.map