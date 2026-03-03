import { escapeHtml } from '../functions/htmlUtils.js';
export const choiceColumnRenderer = {
    matches(column) {
        return typeof column?.choices === 'object' && column.choices !== null;
    },
    configure(column) {
        const choices = (column.choices ?? {});
        const badgesEnabled = true === column.renderAsBadges ||
            (typeof column.renderAsBadges === 'object' && column.renderAsBadges !== null);
        const badges = typeof column.renderAsBadges === 'object' && column.renderAsBadges !== null
            ? column.renderAsBadges
            : {};
        const defaultBadgeVariant = typeof column.defaultBadgeVariant === 'string' ? column.defaultBadgeVariant : 'secondary';
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
            const escapedLabel = escapeHtml(label);
            const escapedVariant = escapeHtml(variant);
            return `<span class="badge text-bg-${escapedVariant}">${escapedLabel}</span>`;
        };
    },
};
//# sourceMappingURL=choiceColumnRenderer.js.map