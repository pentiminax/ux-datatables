import { parseBooleanValue } from '../functions/htmlUtils.js';
import { renderLucideIcon } from '../functions/lucideIcons.js';
export { loadLucideIcons } from '../functions/lucideIcons.js';
const SIZE_PX = { xs: 12, sm: 16, md: 20, lg: 24, xl: 32 };
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
                const svg = renderLucideIcon(iconName, {
                    width: sizePx,
                    height: sizePx,
                });
                if (svg === null) {
                    return '';
                }
                return style.renderIcon(svg, variant, tooltip);
            };
        },
    };
}
//# sourceMappingURL=iconColumnRenderer.js.map