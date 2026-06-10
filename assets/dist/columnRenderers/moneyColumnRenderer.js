import { escapeHtml } from '../functions/htmlUtils.js';
function normalizeMoneyValue(data, storedAsCents) {
    if (data === null || data === undefined || data === '') {
        return null;
    }
    const value = typeof data === 'number' ? data : Number(data);
    if (!Number.isFinite(value)) {
        return null;
    }
    return storedAsCents ? value / 100 : value;
}
function resolveDecimals(value) {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0 && value <= 20
        ? value
        : 2;
}
function resolveCurrency(value) {
    return typeof value === 'string' && /^[A-Z]{3}$/.test(value) ? value : 'EUR';
}
export const moneyColumnRenderer = {
    matches(column) {
        return true === column?.customOptions?.isMoney;
    },
    configure(column) {
        const customOptions = (column.customOptions ?? {});
        const currency = resolveCurrency(customOptions.currency);
        const decimals = resolveDecimals(customOptions.decimals);
        const storedAsCents = false !== customOptions.storedAsCents;
        const showCurrencySign = false !== customOptions.showCurrencySign;
        const locale = typeof customOptions.locale === 'string' ? customOptions.locale : undefined;
        const formatter = new Intl.NumberFormat(locale ?? navigator.language, {
            style: showCurrencySign ? 'currency' : 'decimal',
            ...(showCurrencySign ? { currency } : {}),
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        column.render = (data, type) => {
            const value = normalizeMoneyValue(data, storedAsCents);
            if (type === 'sort' || type === 'type') {
                return value ?? data;
            }
            if (data === null || data === undefined || data === '') {
                return '';
            }
            if (value === null) {
                return escapeHtml(String(data));
            }
            return formatter.format(value);
        };
    },
};
//# sourceMappingURL=moneyColumnRenderer.js.map