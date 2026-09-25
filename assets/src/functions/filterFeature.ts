import type { FilterBar, FilterDefinition } from './filters.js'

let registered = false

function readNestedProperty(obj: any, path: string): any {
    if (!obj || typeof obj !== 'object' || !path) return undefined
    if (path in obj) return obj[path]
    const parts = path.split('.')
    let current = obj
    for (const part of parts) {
        if (current === null || current === undefined || typeof current !== 'object') {
            return undefined
        }
        current = current[part]
    }
    return current
}

export function matchesClientFilters(
    settings: any,
    filterBar: FilterBar,
    searchData: string[],
    rowData: any
): boolean {
    const applied = filterBar.collectValues()
    if (!applied || Object.keys(applied).length === 0) {
        return true
    }

    const definitions = filterBar.getDefinitions()
    const defsByName = new Map<string, FilterDefinition>(definitions.map((d) => [d.name, d]))
    const columns: any[] = settings?.aoColumns ?? []

    for (const [name, val] of Object.entries(applied)) {
        if (val === null || val === undefined || val === '') {
            continue
        }

        const def = defsByName.get(name)
        const colIndex = columns.findIndex(
            (col) => col.sName === name || col.name === name || col.data === name || col.mData === name
        )

        let rawVal: any = undefined
        if (rowData && typeof rowData === 'object' && !Array.isArray(rowData)) {
            rawVal = readNestedProperty(rowData, name)
            if (rawVal === undefined && colIndex !== -1 && columns[colIndex].data) {
                rawVal = readNestedProperty(rowData, String(columns[colIndex].data))
            }
        } else if (Array.isArray(rowData) && colIndex !== -1) {
            rawVal = rowData[colIndex]
        }

        const renderedText =
            colIndex !== -1 && searchData?.[colIndex] !== undefined
                ? String(searchData[colIndex]).trim()
                : ''

        // If the filter target is neither a rendered column nor found in row data, skip it
        if (colIndex === -1 && rawVal === undefined) {
            continue
        }

        const type = def?.type ?? 'text'

        // Checkbox filters in ux-datatables are driven by server-side query closures
        if (type === 'checkbox') {
            continue
        }

        if (type === 'text') {
            const searchStr = String(val).trim().toLowerCase()
            if (!searchStr) continue
            const rowStr =
                rawVal !== undefined && rawVal !== null
                    ? String(rawVal).toLowerCase()
                    : renderedText.toLowerCase()
            if (!rowStr.includes(searchStr) && !renderedText.toLowerCase().includes(searchStr)) {
                return false
            }
        } else if (type === 'select') {
            const options = def?.options ?? {}
            const isRawPresent = rawVal !== undefined && rawVal !== null

            if (def?.multiple && Array.isArray(val)) {
                if (val.length === 0) continue
                const selectedStrings = val.map(String)

                if (Array.isArray(rawVal)) {
                    const rawStrings = rawVal.map(String)
                    if (rawStrings.length === 0 || !selectedStrings.some((s) => rawStrings.includes(s))) {
                        return false
                    }
                } else if (isRawPresent && String(rawVal).trim() !== '') {
                    if (!selectedStrings.includes(String(rawVal).trim())) {
                        return false
                    }
                } else if (renderedText !== '') {
                    const expectedLabels = selectedStrings.map((s) => options[s] ?? s)
                    if (!expectedLabels.includes(renderedText)) {
                        return false
                    }
                } else {
                    return false
                }
            } else {
                const selStr = String(val).trim()
                if (!selStr) continue

                if (isRawPresent && String(rawVal).trim() !== '') {
                    if (String(rawVal).trim() !== selStr) {
                        return false
                    }
                } else if (renderedText !== '') {
                    const expectedLabel = options[selStr] ?? selStr
                    if (renderedText !== expectedLabel) {
                        return false
                    }
                } else {
                    return false
                }
            }
        } else if (type === 'ternary') {
            const norm = String(val).toLowerCase().trim()
            const isTrue = norm === '1' || norm === 'true' || norm === 'yes'
            const isFalse = norm === '0' || norm === 'false' || norm === 'no'

            const isNull = rawVal === null ||
                (rawVal === undefined
                    ? renderedText === ''
                    : typeof rawVal === 'string' && rawVal.trim() === '')

            if (isTrue && isNull) return false
            if (isFalse && !isNull) return false
        } else if (type === 'dateRange') {
            if (typeof val === 'object' && val !== null) {
                const { from, to } = val as { from?: string; to?: string }
                const dateTarget =
                    rawVal !== undefined && rawVal !== null && rawVal !== ''
                        ? String(rawVal)
                        : renderedText
                if (!dateTarget) return false
                const rowTime = parseDateComparable(dateTarget)
                if (rowTime === null) return false

                if (from) {
                    const fromTime = parseDateComparable(from)
                    if (fromTime !== null && rowTime < fromTime) {
                        return false
                    }
                }
                if (to) {
                    const toTime = parseDateUpperBound(to)
                    if (toTime !== null && rowTime > toTime) {
                        return false
                    }
                }
            }
        }
    }

    return true
}

function parseDateComparable(dateStr: string): number | null {
    if (!dateStr) return null
    const trimmed = dateStr.trim()

    // 1. Date only: YYYY-MM-DD or YYYY/MM/DD
    const dateOnlyMatch = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/.exec(trimmed)
    if (dateOnlyMatch) {
        const year = parseInt(dateOnlyMatch[1], 10)
        const month = parseInt(dateOnlyMatch[2], 10) - 1
        const day = parseInt(dateOnlyMatch[3], 10)
        return Date.UTC(year, month, day)
    }

    // 2. Datetime with explicit timezone offset or Z: parse exact UTC timestamp
    if (/[Zz]$|[+-]\d{2}(?::?\d{2})?$/.test(trimmed)) {
        const d = new Date(trimmed)
        return isNaN(d.getTime()) ? null : d.getTime()
    }

    // 3. Timezone-less datetime: YYYY-MM-DD[ T]HH:mm(:ss(.sss)?) -> evaluate in UTC
    const dateTimeMatch =
        /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})[T ](\d{1,2}):(\d{1,2})(?::(\d{1,2})(?:\.(\d{1,3}))?)?$/.exec(
            trimmed
        )
    if (dateTimeMatch) {
        const year = parseInt(dateTimeMatch[1], 10)
        const month = parseInt(dateTimeMatch[2], 10) - 1
        const day = parseInt(dateTimeMatch[3], 10)
        const hour = parseInt(dateTimeMatch[4], 10)
        const min = parseInt(dateTimeMatch[5], 10)
        const sec = dateTimeMatch[6] ? parseInt(dateTimeMatch[6], 10) : 0
        const ms = dateTimeMatch[7] ? parseInt(dateTimeMatch[7].padEnd(3, '0'), 10) : 0
        return Date.UTC(year, month, day, hour, min, sec, ms)
    }

    const d = new Date(trimmed)
    return isNaN(d.getTime()) ? null : d.getTime()
}

function parseDateUpperBound(toStr: string): number | null {
    if (!toStr) return null
    const trimmed = toStr.trim()
    const match = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/.exec(trimmed)
    if (match) {
        const year = parseInt(match[1], 10)
        const month = parseInt(match[2], 10) - 1
        const day = parseInt(match[3], 10)
        return Date.UTC(year, month, day, 23, 59, 59, 999)
    }
    return parseDateComparable(toStr)
}

export function registerFilterFeature(DataTable: any): void {
    if (registered) {
        return
    }
    registered = true

    if (DataTable.ext && Array.isArray(DataTable.ext.search)) {
        DataTable.ext.search.push(
            (settings: any, searchData: string[], dataIndex: number, rowData: any): boolean => {
                const filterBar = settings?._uxFilterBar as FilterBar | undefined
                if (!filterBar) {
                    return true
                }

                if (settings?.oFeatures?.bServerSide || settings?.bServerSide) {
                    return true
                }

                return matchesClientFilters(settings, filterBar, searchData, rowData)
            }
        )
    }

    DataTable.feature.register('filters', (settings: any, opts: any): HTMLElement => {
        const instance = opts?.instance as FilterBar | undefined
        if (!instance) {
            return document.createElement('div')
        }

        if (settings) {
            settings._uxFilterBar = instance
        }

        const api = new DataTable.Api(settings)

        return instance.render(() => {
            const hasAjax = Boolean(
                settings?.ajax ||
                    settings?.sAjaxSource ||
                    settings?.oFeatures?.bServerSide ||
                    (typeof api.ajax?.url === 'function' && Boolean(api.ajax.url())) ||
                    (api.ajax && typeof api.ajax.reload === 'function' && typeof api.draw !== 'function')
            )

            if (hasAjax && api.ajax && typeof api.ajax.reload === 'function') {
                api.ajax.reload(null, true)
            } else if (typeof api.draw === 'function') {
                api.draw()
            }
            if (api.state && typeof api.state.save === 'function') {
                api.state.save()
            }
        })
    })
}

