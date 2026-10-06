import { escapeHtml } from '../functions/htmlUtils.js'
import { type ColumnRenderer, isFormatted } from './types.js'

const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8'])

function hasTypedRenderer(column: Record<string, any>): boolean {
    const options = column.customOptions ?? {}

    return (
        true === options.isMoney ||
        true === options.renderAsSwitch ||
        (typeof options.choices === 'object' && options.choices !== null)
    )
}

/**
 * DataTables inserts Ajax cell values as HTML. A TextColumn is type `string` /
 * `string-utf8`, which only changes sort and search — display still interpolates
 * markup — so user-controlled text would execute unless it is escaped here.
 *
 * `html()` / `html-utf8` columns opt into markup and are left alone.
 */
export const textColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return (
            PLAIN_TEXT_TYPES.has(column?.type) || (isFormatted(column) && hasTypedRenderer(column))
        )
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
