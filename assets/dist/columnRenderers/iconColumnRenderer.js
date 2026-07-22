import { parseBooleanValue } from '../functions/htmlUtils.js';
const SIZE_PX = { xs: 12, sm: 16, md: 20, lg: 24, xl: 32 };
let lucide = null;
export async function loadLucideIcons() {
    if (lucide === null) {
        lucide = (await import('lucide'));
    }
}
function kebabToPascal(name) {
    return name
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('');
}
function renderSvg(iconName, sizePx) {
    if (lucide === null || iconName.length === 0) {
        return null;
    }
    const iconNode = lucide.icons[kebabToPascal(iconName)];
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
            const icons = customOptions.icons ?? {};
            const colors = customOptions.colors ?? {};
            const tooltips = customOptions.tooltips ?? {};
            const defaultIcon = customOptions.defaultIcon ?? '';
            const defaultColor = customOptions.defaultColor ?? '';
            const sizePx = SIZE_PX[customOptions.size ?? 'md'] ?? SIZE_PX.md;
            const booleanMode = true === customOptions.boolean;
            column.render = (data, type) => {
                if (type !== 'display') {
                    return data;
                }
                let iconName;
                let variant;
                let tooltip;
                if (booleanMode) {
                    const on = parseBooleanValue(data);
                    iconName =
                        (on ? customOptions.trueIcon : customOptions.falseIcon) ?? defaultIcon;
                    variant =
                        (on ? customOptions.trueColor : customOptions.falseColor) ?? defaultColor;
                    tooltip = '';
                }
                else {
                    const key = String(data ?? '');
                    iconName = icons[key] ?? defaultIcon;
                    variant = colors[key] ?? defaultColor;
                    tooltip = tooltips[key] ?? '';
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