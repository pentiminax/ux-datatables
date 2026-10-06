import { escapeHtml } from '../functions/htmlUtils.js'
import { type ColumnRenderer, isFormatted } from './types.js'

const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8'])
const HTML_TYPES = new Set(['html', 'html-utf8'])

function isFormattedText(column: Record<string, any>): boolean {
    if (!isFormatted(column)) {
        return false
    }

    const choices = column.customOptions?.choices

    return !HTML_TYPES.has(column.type) || (typeof choices === 'object' && choices !== null)
}

/**
 * DataTables inserts Ajax cell values as HTML. A TextColumn is type `string` /
 * `string-utf8`, which only changes sort and search — display still interpolates
 * markup — so user-controlled text would execute unless it is escaped here.
 *
 * `html()` / `html-utf8` columns opt into markup and are left alone. A column formatted on the
 * server carries a display string whatever its type, so it is escaped here too, and the controller
 * hands it to no other renderer.
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
