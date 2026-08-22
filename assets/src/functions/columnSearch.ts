import type { StyleFramework } from '../types/styleFramework.js'
import { inputClass } from './filters.js'

/**
 * Column definition as serialized by AbstractColumn::jsonSerialize().
 * Only the fields relevant to tfoot search are typed here.
 */
interface ColumnDefinition {
    searchable: boolean
    title?: string
    [key: string]: unknown
}

/**
 * Return true when the payload requests tfoot column search inputs.
 */
export function hasTfootSearch(payload: Record<string, any>): boolean {
    return payload.tfootSearch === true
}

/**
 * Inject a tfoot row of per-column search inputs into the DataTable.
 *
 * Sets payload.initComplete so that once DataTables.net has fully initialised
 * the table (and built its thead), a <tfoot><tr> is appended with one <th>
 * per column: a <input type="search"> for searchable columns, an empty <th>
 * for non-searchable ones (action columns, checkbox columns, etc.).
 *
 * Each input fires a debounced `input` event handler that calls
 * api.column(i).search(value).draw(), which sends columns[i][search][value]
 * in the next Ajax request. The backend's ColumnSearchFilter picks this up
 * and applies the column's configured search field, joins, and predicate.
 * Paging is reset on each new search so the user always sees page 1 of results.
 *
 * Any existing payload.initComplete callback (set via customOptions or the
 * datatables:pre-connect event) is called first so user-defined callbacks are
 * not overwritten.
 *
 * @param payload   The DataTables configuration object (mutated in place)
 * @param framework The detected style framework for input CSS classes
 * @param DataTable The DataTables constructor (used to wrap settings into an Api instance)
 */
const DEBOUNCE_MS = 300

function debounce(fn: () => void, delay: number): () => void {
    let timer: ReturnType<typeof setTimeout> | undefined
    return () => {
        clearTimeout(timer)
        timer = setTimeout(fn, delay)
    }
}

export function applyTfootColumnSearch(
    payload: Record<string, any>,
    framework: StyleFramework,
    DataTable: any
): void {
    const columns: ColumnDefinition[] = Array.isArray(payload.columns) ? payload.columns : []
    const prior: ((...args: any[]) => void) | undefined = payload.initComplete

    payload.initComplete = function (settings: any, data: any): void {
        if (prior) {
            prior.call(this, settings, data)
        }

        const api = new DataTable.Api(settings)
        const tableEl = api.table().node() as HTMLTableElement

        let tfoot = tableEl.querySelector('tfoot')
        if (!tfoot) {
            tfoot = document.createElement('tfoot')
            tableEl.appendChild(tfoot)
        }

        const row = document.createElement('tr')

        columns.forEach((column, index) => {
            const th = document.createElement('th')

            if (column.searchable) {
                const input = document.createElement('input')
                input.type = 'search'
                input.className = inputClass(framework)
                input.placeholder = typeof column.title === 'string' ? column.title : ''
                input.setAttribute('aria-label', input.placeholder)

                input.addEventListener(
                    'input',
                    debounce(() => {
                        api.column(index).search(input.value).draw()
                    }, DEBOUNCE_MS),
                )

                th.appendChild(input)
            }

            row.appendChild(th)
        })

        tfoot.appendChild(row)
    }
}
