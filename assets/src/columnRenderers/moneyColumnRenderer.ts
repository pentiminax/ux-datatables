import { escapeHtml } from '../functions/htmlUtils.js'
import type { ColumnRenderer, MoneyCustomOptions } from './types.js'

function normalizeMoneyValue(data: unknown, storedAsCents: boolean): number | null {
    if (data === null || data === undefined || data === '') {
        return null
    }

    const value = typeof data === 'number' ? data : Number(data)
    if (!Number.isFinite(value)) {
        return null
    }

    return storedAsCents ? value / 100 : value
}

function resolveDecimals(value: unknown): number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0 && value <= 20
        ? value
        : 2
}

function resolveCurrency(value: unknown): string {
    return typeof value === 'string' && /^[A-Z]{3}$/.test(value) ? value : 'EUR'
}

export const moneyColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return true === column?.customOptions?.isMoney
    },

    configure(column: Record<string, any>): void {
        const customOptions = (column.customOptions ?? {}) as MoneyCustomOptions
        const currency = resolveCurrency(customOptions.currency)
        const decimals = resolveDecimals(customOptions.decimals)
        const storedAsCents = false !== customOptions.storedAsCents
        const locale = typeof customOptions.locale === 'string' ? customOptions.locale : undefined
        const formatter = new Intl.NumberFormat(locale ?? navigator.language, {
            style: 'currency',
            currency,
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        })

        column.render = (data: any, type: string): any => {
            const value = normalizeMoneyValue(data, storedAsCents)

            if (type === 'sort' || type === 'type') {
                return value ?? data
            }

            if (data === null || data === undefined || data === '') {
                return ''
            }

            if (value === null) {
                return escapeHtml(String(data))
            }

            return formatter.format(value)
        }
    },
}
