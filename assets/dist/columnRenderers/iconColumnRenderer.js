import { parseBooleanValue } from '../functions/htmlUtils.js';
const SIZE_PX = { xs: 12, sm: 16, md: 20, lg: 24, xl: 32 };
let lucide = null;
let iconsByKebab = null;
function pascalToKebab(name) {
    return name
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase();
}
export async function loadLucideIcons() {
    if (lucide === null) {
        lucide = (await import('lucide'));
        iconsByKebab = new Map();
        for (const [pascal, node] of Object.entries(lucide.icons)) {
            iconsByKebab.set(pascalToKebab(pascal), node);
        }
    }
}
function renderSvg(iconName, sizePx) {
    if (lucide === null || iconsByKebab === null || iconName.length === 0) {
        return null;
    }
    const iconNode = iconsByKebab.get(iconName);
    if (iconNode === undefined) {
        return null;
    }
    return lucide.createElement(iconNode, { width: sizePx, height: sizePx }).outerHTML;
}
export function createIconColumnRenderer(style) {
    return {
        matches(column) {
            return true === column?.customOptions?.isIcon;
        },
        configure(column) {
            const customOptions = (column.customOptions ?? {});
            const columnKey = column.data ?? column.name;
            const tooltips = customOptions.tooltips ?? {};
            const staticIcon = customOptions.icon ?? '';
            const staticColor = customOptions.color ?? '';
            const sizePx = SIZE_PX[customOptions.size ?? 'md'] ?? SIZE_PX.md;
            const booleanMode = true === customOptions.boolean;
            column.render = (data, type, row) => {
                if (type !== 'display') {
                    return data;
                }
                let iconName;
                let variant;
                let tooltip;
                if (booleanMode) {
                    const on = parseBooleanValue(data);
                    iconName = (on ? customOptions.trueIcon : customOptions.falseIcon) ?? staticIcon;
                    variant =
                        (on ? customOptions.trueColor : customOptions.falseColor) ?? staticColor;
                    tooltip = '';
                }
                else {
                    const resolved = row?.__ux_datatables_icons?.[columnKey];
                    iconName = resolved?.icon ?? staticIcon;
                    variant = resolved?.color ?? staticColor;
                    tooltip = tooltips[String(data ?? '')] ?? '';
                }
                const svg = renderSvg(iconName, sizePx);
                if (svg === null) {
                    return '';
                }
                return style.renderIcon(svg, variant, tooltip);
            };
        },
    };
}
//# sourceMappingURL=iconColumnRenderer.js.map