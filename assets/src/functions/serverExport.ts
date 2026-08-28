import { buttonActions } from './buttonActionRegistry.js'

export const SERVER_EXPORT_ACTION = 'ux:export'

type AjaxApi = {
    ajax?: {
        params?: () => Record<string, unknown>
    }
}

export function flattenFormValues(
    data: unknown,
    prefix = '',
    acc: Array<{ name: string; value: string }> = []
): Array<{ name: string; value: string }> {
    if (data === null || data === undefined) {
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

export function applyServerExportUrls(payload: Record<string, unknown>): void {
    const url = payload.exportUrl
    if (typeof url !== 'string' || url === '') {
        return
    }

    const layout = payload.layout
    if (typeof layout === 'object' && layout !== null && !Array.isArray(layout)) {
        const record = layout as Record<string, unknown>
        for (const position of Object.keys(record)) {
            stampExportUrl(record[position], url)
        }
    }

    delete payload.exportUrl
}

function stampExportUrl(value: unknown, url: string): void {
    if (Array.isArray(value)) {
        for (const item of value) {
            stampExportUrl(item, url)
        }

        return
    }

    if (typeof value !== 'object' || value === null) {
        return
    }

    const record = value as Record<string, unknown>
    if (record.action === SERVER_EXPORT_ACTION && typeof record.url !== 'string') {
        record.url = url
    }

    for (const key of ['buttons', 'postfixButtons', 'prefixButtons'] as const) {
        if (Array.isArray(record[key])) {
            stampExportUrl(record[key], url)
        }
    }
}

export function runServerExport(
    _e: Event,
    dt: unknown,
    _node: HTMLElement,
    config: Record<string, unknown>
): void {
    const ajax = (dt as AjaxApi).ajax
    if (typeof ajax?.params !== 'function') {
        console.error('Server-side export requires an Ajax DataTable.')

        return
    }

    const url = typeof config.url === 'string' ? config.url : ''
    if (url === '') {
        console.error('Server-side export is missing an export URL.')

        return
    }

    const params: Record<string, unknown> = {
        ...ajax.params(),
        start: 0,
        length: 0,
        exportKey: typeof config.exportKey === 'string' ? config.exportKey : 'csv',
        format: typeof config.format === 'string' ? config.format : 'csv',
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
