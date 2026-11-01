import { isRecord } from './htmlUtils.js'

export function forwardedQuery(payload: Record<string, any>): Record<string, unknown> {
    return isRecord(payload.forwardedQuery) ? payload.forwardedQuery : {}
}
