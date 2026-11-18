import type { DataTableView } from '../types/dataTableView.js'
import { isRecord } from './htmlUtils.js'

export function forwardedQuery(payload: DataTableView): Record<string, unknown> {
    return isRecord(payload.forwardedQuery) ? payload.forwardedQuery : {}
}
