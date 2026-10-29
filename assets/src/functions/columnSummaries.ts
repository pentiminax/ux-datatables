export type SummaryType = 'sum' | 'avg' | 'min' | 'max' | 'count'

interface SummaryDefinition {
    type: SummaryType
    label?: string
}

interface SummaryColumn {
    name?: string
    summaries?: SummaryDefinition[]
    customOptions?: { isMoney?: boolean }
    render?: (data: unknown, type: string) => unknown
}

const DEFAULT_LABELS: Record<SummaryType, string> = {
    sum: 'Total',
    avg: 'Average',
    min: 'Minimum',
    max: 'Maximum',
    count: 'Count',
}

export function hasSummaries(payload: Record<string, any>): boolean {
    return (
        Array.isArray(payload?.columns) &&
        payload.columns.some((column: SummaryColumn) => (column?.summaries?.length ?? 0) > 0)
    )
}

/** Mirrors SummaryType::aggregate() in PHP: null and empty values are ignored. */
export function aggregate(type: SummaryType, values: unknown[]): number | string | null {
    const present = values.filter((value) => value !== null && value !== undefined && value !== '')

    if (type === 'count') {
        return present.length
    }

    const numbers = present.map(Number).filter(Number.isFinite)

    switch (type) {
        case 'sum':
            return present.length === 0 ? null : numbers.reduce((total, n) => total + n, 0)
        case 'avg':
            return numbers.length === 0 ? null : numbers.reduce((t, n) => t + n, 0) / numbers.length
        default: {
            if (present.length === 0) {
                return null
            }
            const pool = numbers.length === present.length ? numbers : present.map(String)
            const sorted = [...pool].sort((a, b) => (a < b ? -1 : a > b ? 1 : 0))

            return sorted[type === 'min' ? 0 : sorted.length - 1]
        }
    }
}

function format(column: SummaryColumn, type: SummaryType, value: number | string | null): string {
    if (value === null || value === undefined) {
        return ''
    }

    if (column.customOptions?.isMoney && type !== 'count' && typeof column.render === 'function') {
        return String(column.render(value, 'display'))
    }

    return typeof value === 'number'
        ? new Intl.NumberFormat(navigator.language).format(value)
        : value
}

function fill(
    cell: Element | null,
    column: SummaryColumn,
    values: Partial<Record<SummaryType, number | string | null>>,
    labels: Record<string, string>
): void {
    if (!cell) {
        return
    }

    cell.replaceChildren()

    for (const { type, label } of column.summaries ?? []) {
        const line = document.createElement('div')
        line.className = 'dt-summary'

        const name = document.createElement('span')
        name.className = 'dt-summary__label'
        name.textContent = label ?? labels[type] ?? DEFAULT_LABELS[type]

        const value = document.createElement('span')
        value.className = 'dt-summary__value'
        value.textContent = format(column, type, values[type] ?? null)

        line.append(name, ' ', value)
        cell.append(line)
    }
}

/** Must run before DataTables initializes: it only registers a footer present at that moment. */
export function ensureSummaryFooter(tableElement: HTMLElement, payload: Record<string, any>): void {
    if (tableElement.querySelector('tfoot')) {
        return
    }

    const columnCount: number = payload.columns.length

    const row = document.createElement('tr')
    for (let index = 0; index < columnCount; index++) {
        const cell = document.createElement('th')
        cell.scope = 'col'
        row.append(cell)
    }

    const footer = document.createElement('tfoot')
    footer.append(row)
    tableElement.append(footer)
}

/**
 * Renders summary totals in the table footer: from `json.summaries` on server-side tables (every
 * matching row, computed by the server), from the searched rows on client-side ones.
 */
export function installColumnSummaries(table: any, payload: Record<string, any>): void {
    const columns: SummaryColumn[] = payload.columns
    const labels: Record<string, string> = payload.summaryLabels ?? {}

    columns.forEach((column, index) => {
        if (column.summaries?.length) {
            fill(table.column(index).footer(), column, {}, labels)
        }
    })

    if (payload.serverSide) {
        table.on('xhr.dt', (_event: unknown, _settings: unknown, json: any) => {
            columns.forEach((column, index) => {
                if (column.summaries?.length) {
                    fill(
                        table.column(index).footer(),
                        column,
                        json?.summaries?.[column.name ?? ''] ?? {},
                        labels
                    )
                }
            })
        })

        return
    }

    const update = (): void => {
        columns.forEach((column, index) => {
            if (!column.summaries?.length) {
                return
            }

            const rows: unknown[] = table.column(index, { search: 'applied' }).data().toArray()
            const values: Partial<Record<SummaryType, number | string | null>> = {}
            for (const { type } of column.summaries) {
                values[type] = aggregate(type, rows)
            }

            fill(table.column(index).footer(), column, values, labels)
        })
    }

    table.on('draw.dt', update)
    update()
}
