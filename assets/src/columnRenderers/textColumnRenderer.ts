import { escapeHtml } from '../functions/htmlUtils.js'
import { type ColumnRenderer, isFormatted } from './types.js'

const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8'])
const HTML_TYPES = new Set(['html', 'html-utf8', 'html-num', 'html-num-fmt'])

/**
 * Columns that use a DataTables html* type for their own renderer, not because the developer
 * called html(). formatValueUsing() still owes them escaping.
 */
function isSpecializedHtmlColumn(column: Record<string, any>): boolean {
    const options = column.customOptions ?? {}

    return (
        true === options.isEmail ||
        true === options.isUrl ||
        true === options.isIcon ||
        true === options.isImage ||
        (typeof options.choices === 'object' && options.choices !== null)
    )
}

function isFormattedText(column: Record<string, any>): boolean {
    if (!isFormatted(column)) {
        return false
    }

    // TextColumn::html() / NumberColumn::html() opt into markup from the formatter.
    return !HTML_TYPES.has(column.type) || isSpecializedHtmlColumn(column)
}

/**
 * DataTables inserts Ajax cell values as HTML and the `string` type only affects sort and search,
 * so the text is escaped here. `html()` columns opt into markup; server-formatted columns are
 * escaped whatever their type, including Email/Url/Icon/Image/Choice which only use type html for
 * DataTables.
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
