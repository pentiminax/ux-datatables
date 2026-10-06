import { escapeHtml } from '../functions/htmlUtils.js'
import { type ColumnRenderer, isFormatted } from './types.js'

const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8'])
const HTML_TYPES = new Set(['html', 'html-utf8', 'html-num', 'html-num-fmt'])

function isFormattedText(column: Record<string, any>): boolean {
    if (!isFormatted(column)) {
        return false
    }

    const choices = column.customOptions?.choices

    return !HTML_TYPES.has(column.type) || (typeof choices === 'object' && choices !== null)
}

/**
 * DataTables inserts Ajax cell values as HTML and the `string` type only affects sort and search,
 * so the text is escaped here. `html()` columns opt into markup; server-formatted columns are
 * escaped whatever their type.
 */
export const textColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return PLAIN_TEXT_TYPES.has(column?.type) || isFormattedText(column)
    },

    configure(column: Record<string, any>): void {
        if (typeof column.render === 'function') {
            return
        }

        column.render = (data: unknown, type: string): unknown => {
            if (type !== 'display') {
                return data
            }

            if (data === null || data === undefined) {
                return data
            }

            return escapeHtml(String(data))
        }
    },
}
