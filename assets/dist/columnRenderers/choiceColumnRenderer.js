import { escapeHtml } from '../functions/htmlUtils.js';
export function createChoiceColumnRenderer(style) {
    return {
        matches(column) {
            return (typeof column?.customOptions?.choices === 'object' &&
                column.customOptions.choices !== null);
        },
        configure(column) {
            const customOptions = (column.customOptions ?? {});
            const choices = (customOptions.choices ?? {});
            const badgesEnabled = true === customOptions.renderAsBadges ||
                (typeof customOptions.renderAsBadges === 'object' &&
                    customOptions.renderAsBadges !== null);
            const badges = typeof customOptions.renderAsBadges === 'object' &&
                customOptions.renderAsBadges !== null
                ? customOptions.renderAsBadges
                : {};
            const defaultBadgeVariant = customOptions.defaultBadgeVariant || 'secondary';
            column.render = (data, type) => {
                const key = String(data ?? '');
                const label = choices[key] ?? key;
                if (type !== 'display') {
                    return label;
                }
                if (!badgesEnabled) {
                    return escapeHtml(label);
                }
                const variant = badges[key] ?? defaultBadgeVariant;
                return style.renderBadge(label, variant);
            };
        },
    };
}
//# sourceMappingURL=choiceColumnRenderer.js.map