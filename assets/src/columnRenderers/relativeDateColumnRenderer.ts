import { escapeHtml } from '../functions/htmlUtils.js'
import type { ColumnRenderer, DateCustomOptions } from './types.js'

const THRESHOLDS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.34524],
    ['month', 12],
    ['year', Number.POSITIVE_INFINITY],
]

function resolveLocale(customOptions: DateCustomOptions): string {
    if (typeof customOptions.locale === 'string' && customOptions.locale !== '') {
        return customOptions.locale
    }

    return document.documentElement.lang || navigator.language
}

function toRelativeLabel(formatter: Intl.RelativeTimeFormat, timestamp: number): string {
    let delta = (timestamp - Date.now()) / 1000

    for (const [unit, step] of THRESHOLDS) {
        if (Math.abs(delta) < step) {
            return formatter.format(Math.round(delta), unit)
        }

        delta /= step
    }

    return formatter.format(Math.round(delta), 'year')
}

export const relativeDateColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return true === column?.customOptions?.relative
    },

    configure(column: Record<string, any>): void {
        const customOptions = (column.customOptions ?? {}) as DateCustomOptions
        const formatter = new Intl.RelativeTimeFormat(resolveLocale(customOptions), {
            numeric: 'auto',
        })

        column.render = (data: any, type: string): any => {
            if (type === 'sort' || type === 'type' || type === 'filter') {
                return data
            }

            if (data === null || data === undefined || data === '') {
                return ''
            }

            const timestamp = Date.parse(String(data))
            if (Number.isNaN(timestamp)) {
                return escapeHtml(String(data))
            }

            return toRelativeLabel(formatter, timestamp)
        }
    },
}
