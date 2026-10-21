import { buttonActions } from './buttonActionRegistry.js'

export const SERVER_EXPORT_ACTION = 'ux:export'

type AjaxApi = {
    ajax?: {
        params?: () => Record<string, unknown>
    }
}

/**
 * Reads the DataTables-protocol params of the last Ajax request (paging, search, filters,
 * columns) before any transport rewrite. Same snapshot bulk select-all replays.
 */
export type ProtocolParamsProvider = () => Record<string, unknown>

type ServerExportConfig = Record<string, unknown> & {
    url?: string
    exportKey?: string
    format?: string
    /**
     * Set by {@link applyServerExportUrls} when the controller installed request params.
     * Preferred over `ajax.params()`, which holds the wire format after a transport rewrite.
     */
    protocolParams?: ProtocolParamsProvider
}

export function flattenFormValues(
    data: unknown,
    prefix = '',
    acc: Array<{ name: string; value: string }> = []
): Array<{ name: string; value: string }> {
    // jQuery.param (DataTables' own Ajax serializer) turns null into an empty string.
    // Skipping the field left Select's checkbox column without `name`/`data`, and the
    // PHP Column parser TypeError'd on the export POST.
    if (data === null || data === undefined) {
        if (prefix !== '') {
            acc.push({ name: prefix, value: '' })
        }

        return acc
    }

    if (Array.isArray(data)) {
        for (const [index, item] of data.entries()) {
            flattenFormValues(item, prefix === '' ? String(index) : `${prefix}[${index}]`, acc)
        }

        return acc
    }

    if (typeof data === 'object') {
        for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
            flattenFormValues(value, prefix === '' ? key : `${prefix}[${key}]`, acc)
        }

        return acc
    }

    if (prefix !== '') {
        acc.push({ name: prefix, value: String(data) })
    }

    return acc
}

export function applyServerExportUrls(
    payload: Record<string, unknown>,
    protocolParams?: ProtocolParamsProvider
): void {
    const url = payload.exportUrl
    if (typeof url !== 'string' || url === '') {
        return
    }

    const layout = payload.layout
    if (typeof layout === 'object' && layout !== null && !Array.isArray(layout)) {
        const record = layout as Record<string, unknown>
        for (const position of Object.keys(record)) {
            stampExportUrl(record[position], url, protocolParams)
        }
    }

    delete payload.exportUrl
}

function stampExportUrl(
    value: unknown,
    url: string,
    protocolParams: ProtocolParamsProvider | undefined
): void {
    if (Array.isArray(value)) {
        for (const item of value) {
            stampExportUrl(item, url, protocolParams)
        }

        return
    }

    if (typeof value !== 'object' || value === null) {
        return
    }

    const record = value as ServerExportConfig
    if (record.action === SERVER_EXPORT_ACTION && typeof record.url !== 'string') {
        record.url = url
        if (protocolParams) {
            record.protocolParams = protocolParams
        }
    }

    for (const key of ['buttons', 'postfixButtons', 'prefixButtons'] as const) {
        if (Array.isArray(record[key])) {
            stampExportUrl(record[key], url, protocolParams)
        }
    }
}

/**
 * The last Ajax params in DataTables protocol form. Falls back to `ajax.params()` only when the
 * controller never installed a request-params handle (no filters, bulk, or API Platform transport).
 */
export function resolveExportParams(
    dt: unknown,
    config: ServerExportConfig
): Record<string, unknown> {
    if (typeof config.protocolParams === 'function') {
        return config.protocolParams()
    }

    const ajax = (dt as AjaxApi).ajax
    if (typeof ajax?.params === 'function') {
        const wire = ajax.params()

        return wire !== null && typeof wire === 'object' && !Array.isArray(wire) ? wire : {}
    }

    return {}
}

export function runServerExport(
    _e: Event,
    dt: unknown,
    _node: HTMLElement,
    config: Record<string, unknown>
): void {
    const exportConfig = config as ServerExportConfig
    const protocol = resolveExportParams(dt, exportConfig)

    if (
        typeof exportConfig.protocolParams !== 'function' &&
        typeof (dt as AjaxApi).ajax?.params !== 'function'
    ) {
        console.error('Server-side export requires an Ajax DataTable.')

        return
    }

    const url = typeof exportConfig.url === 'string' ? exportConfig.url : ''
    if (url === '') {
        console.error('Server-side export is missing an export URL.')

        return
    }

    const params: Record<string, unknown> = {
        ...protocol,
        start: 0,
        length: 0,
        exportKey: typeof exportConfig.exportKey === 'string' ? exportConfig.exportKey : 'csv',
        format: typeof exportConfig.format === 'string' ? exportConfig.format : 'csv',
    }

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = url
    form.style.display = 'none'

    for (const field of flattenFormValues(params)) {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = field.name
        input.value = field.value
        form.appendChild(input)
    }

    document.body.appendChild(form)
    form.submit()
    form.remove()
}

buttonActions.register(SERVER_EXPORT_ACTION, runServerExport)
