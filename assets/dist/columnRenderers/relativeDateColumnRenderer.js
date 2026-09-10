import { escapeHtml } from '../functions/htmlUtils.js';
const THRESHOLDS = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.34524],
    ['month', 12],
    ['year', Number.POSITIVE_INFINITY],
];
function resolveLocale(customOptions) {
    if (typeof customOptions.locale === 'string' && customOptions.locale !== '') {
        return customOptions.locale;
    }
    return document.documentElement.lang || navigator.language;
}
function toRelativeLabel(formatter, timestamp) {
    let delta = (timestamp - Date.now()) / 1000;
    for (const [unit, step] of THRESHOLDS) {
        if (Math.abs(delta) < step) {
            return formatter.format(Math.round(delta), unit);
        }
        delta /= step;
    }
    return formatter.format(Math.round(delta), 'year');
}
export const relativeDateColumnRenderer = {
    matches(column) {
        return true === column?.customOptions?.relative;
    },
    configure(column) {
        const customOptions = (column.customOptions ?? {});
        const formatter = new Intl.RelativeTimeFormat(resolveLocale(customOptions), {
            numeric: 'auto',
        });
        column.render = (data, type) => {
            if (type === 'sort' || type === 'type' || type === 'filter') {
                return data;
            }
            if (data === null || data === undefined || data === '') {
                return null;
            }
            const timestamp = Date.parse(String(data));
            if (Number.isNaN(timestamp)) {
                return escapeHtml(String(data));
            }
            return toRelativeLabel(formatter, timestamp);
        };
    },
};
//# sourceMappingURL=relativeDateColumnRenderer.js.map