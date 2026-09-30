import { escapeHtml } from '../functions/htmlUtils.js'
import type { ColumnRenderer } from './types.js'

const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8'])

/**
 * DataTables inserts Ajax cell values as HTML. A TextColumn is type `string` /
 * `string-utf8`, which only changes sort and search — display still interpolates
 * markup — so user-controlled text would execute unless it is escaped here.
 *
 * `html()` / `html-utf8` columns opt into markup and are left alone.
 */
export const textColumnRenderer: ColumnRenderer = {
    matches(column: Record<string, any>): boolean {
        return PLAIN_TEXT_TYPES.has(column?.type)
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
