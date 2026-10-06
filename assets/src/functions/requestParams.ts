import { isRecord } from './htmlUtils.js'

export type RequestParams = Record<string, any>

export interface RequestParamsOptions {
    /** Read on every request and sent as `filters`. */
    filters?: () => Record<string, unknown>
    /** Rewrites the DataTables params into another wire format, such as API Platform's. */
    transport?: (params: RequestParams) => Record<string, string>
}

export interface RequestParamsHandle {
    /**
     * The DataTables-protocol params of the last request: user `ajax.data` and filters applied,
     * before any transport rewrite or serialization. A bulk select-all replays them server-side,
     * so it must not read `ajax.params()`, which holds whatever went on the wire.
     */
    current(): RequestParams
}

/**
 * The single owner of the request a server-side table sends: it composes the user's `ajax.data`,
 * the filter values and an optional transport, in that order.
 */
export function installRequestParams(
    payload: Record<string, any>,
    options: RequestParamsOptions = {}
): RequestParamsHandle {
    let last: RequestParams = {}
    const handle: RequestParamsHandle = { current: () => last }

    if (typeof payload.ajax === 'function') {
        const originalAjax = payload.ajax
        payload.ajax = (data: RequestParams, ...rest: unknown[]) => {
            if (options.filters) {
                data.filters = options.filters()
            }
            last = data

            return originalAjax(data, ...rest)
        }

        return handle
    }

    if (!isRecord(payload.ajax)) {
        return handle
    }

    const userData = payload.ajax.data
    payload.ajax.data = (data: RequestParams, settings?: unknown): RequestParams | string => {
        // Merged first so the applied filter values win over static `filters`.
        if (isRecord(userData)) {
            Object.assign(data, userData)
        }
        if (options.filters) {
            data.filters = options.filters()
        }

        const sent = typeof userData === 'function' ? callUserData(data, userData, settings) : data
        const returned = (typeof sent === 'string' ? parseJsonRecord(sent) : sent) ?? data
        // A transport rewrites the whole request, so it needs the paging, search and ordering the
        // callback left out as well as every change the callback made.
        const params = options.transport ? { ...data, ...returned } : returned

        // A callback that returns a replacement object keeps the filters unless it set its own.
        if (options.filters && undefined === params.filters) {
            params.filters = data.filters
        }
        last = params

        if (options.transport) {
            return options.transport(params)
        }

        return sent
    }

    return handle
}

/**
 * A callback may mutate the params, return a replacement object, or return a serialized string
 * that goes on the wire as is.
 */
function callUserData(
    data: RequestParams,
    userData: (data: RequestParams, settings: unknown) => unknown,
    settings: unknown
): RequestParams | string {
    const returned = userData(data, settings)

    return typeof returned === 'string' || isRecord(returned) ? returned : data
}

/** A JSON string is what was actually sent, so it is remembered over the params behind it. */
function parseJsonRecord(sent: string): RequestParams | null {
    try {
        const parsed: unknown = JSON.parse(sent)

        return isRecord(parsed) ? parsed : null
    } catch {
        return null
    }
}
