export interface UrlStateConfig {
    search: boolean
    order: boolean
    page: boolean
    pageLength: boolean
    prefix: string
}

export interface OrderEntry {
    name: string
    dir: 'asc' | 'desc'
}

export interface UrlStateSnapshot {
    search?: string
    order?: OrderEntry | OrderEntry[]
    start?: number
    pageLength?: number
}

export function isUrlStateEnabled(payload: Record<string, any>): UrlStateConfig | null {
    const cfg = payload?.urlState

    if (!cfg || typeof cfg !== 'object') return null

    if (!cfg.search && !cfg.order && !cfg.page && !cfg.pageLength) return null

    return {
        search: !!cfg.search,
        order: !!cfg.order,
        page: !!cfg.page,
        pageLength: !!cfg.pageLength,
        prefix: typeof cfg.prefix === 'string' ? cfg.prefix : '',
    }
}

function paramName(cfg: UrlStateConfig, key: 'search' | 'start' | 'pageLength'): string {
    return cfg.prefix ? `${cfg.prefix}[${key}]` : key
}

function orderBase(cfg: UrlStateConfig): string {
    return cfg.prefix ? `${cfg.prefix}[order]` : 'order'
}

function clearOrderParams(params: URLSearchParams, base: string): void {
    const toDelete: string[] = []
    params.forEach((_, key) => {
        if (key.startsWith(`${base}[`)) toDelete.push(key)
    })
    toDelete.forEach((key) => params.delete(key))
}

function resolveColumnName(table: any, idx: number): string {
    try {
        return table.column(idx).name() as string
    } catch {
        return String(idx)
    }
}

function buildOrderEntries(table: any): OrderEntry[] {
    const raw = table.order() as Array<
        [number, string] | { name?: string; idx?: number; dir: string }
    >

    if (!raw || raw.length === 0) return []

    return raw.map((o): OrderEntry => {
        if (Array.isArray(o)) {
            return { name: resolveColumnName(table, o[0]), dir: o[1] as 'asc' | 'desc' }
        }
        if (o.name) return { name: o.name, dir: o.dir as 'asc' | 'desc' }
        return {
            name: o.idx !== undefined ? resolveColumnName(table, o.idx) : '',
            dir: o.dir as 'asc' | 'desc',
        }
    })
}

export function readUrlState(cfg: UrlStateConfig, search = window.location.search): UrlStateSnapshot {
    const params = new URLSearchParams(search)
    const out: UrlStateSnapshot = {}

    if (cfg.search) {
        const v = params.get(paramName(cfg, 'search'))
        if (v !== null) out.search = v
    }

    if (cfg.order) {
        const base = orderBase(cfg)
        const name = params.get(`${base}[name]`)
        const dir = params.get(`${base}[dir]`)

        if (name && (dir === 'asc' || dir === 'desc')) {
            out.order = { name, dir }
        } else {
            const multi: OrderEntry[] = []
            let i = 0
            while (true) {
                const n = params.get(`${base}[${i}][name]`)
                const d = params.get(`${base}[${i}][dir]`)
                if (!n || (d !== 'asc' && d !== 'desc')) break
                multi.push({ name: n, dir: d })
                i++
            }
            if (multi.length > 0) out.order = multi
        }
    }

    if (cfg.page) {
        const v = params.get(paramName(cfg, 'start'))
        if (v !== null) {
            const n = parseInt(v, 10)
            if (Number.isFinite(n) && n >= 0) out.start = n
        }
    }

    if (cfg.pageLength) {
        const v = params.get(paramName(cfg, 'pageLength'))
        if (v !== null) {
            const n = parseInt(v, 10)
            if (Number.isFinite(n) && n > 0) out.pageLength = n
        }
    }

    return out
}

export function applyUrlStateToPayload(
    payload: Record<string, any>,
    snapshot: UrlStateSnapshot
): void {
    if (snapshot.search !== undefined) {
        if (payload.search && typeof payload.search === 'object') {
            payload.search.search = snapshot.search
        } else {
            payload.search = { search: snapshot.search }
        }
    }

    if (snapshot.order !== undefined) payload.order = snapshot.order
    if (snapshot.start !== undefined) payload.displayStart = snapshot.start
    if (snapshot.pageLength !== undefined) payload.pageLength = snapshot.pageLength
}

export function writeUrlState(cfg: UrlStateConfig, table: any): void {
    const params = new URLSearchParams(window.location.search)

    if (cfg.search) {
        const name = paramName(cfg, 'search')
        const s = table.search() as string
        s ? params.set(name, s) : params.delete(name)
    }

    if (cfg.order) {
        const base = orderBase(cfg)
        clearOrderParams(params, base)
        const entries = buildOrderEntries(table)

        if (entries.length === 1) {
            params.set(`${base}[name]`, entries[0].name)
            params.set(`${base}[dir]`, entries[0].dir)
        } else if (entries.length > 1) {
            entries.forEach((o, i) => {
                params.set(`${base}[${i}][name]`, o.name)
                params.set(`${base}[${i}][dir]`, o.dir)
            })
        }
    }

    if (cfg.page) {
        const name = paramName(cfg, 'start')
        const start = (table.page.info() as { start: number }).start
        start > 0 ? params.set(name, String(start)) : params.delete(name)
    }

    if (cfg.pageLength) {
        params.set(paramName(cfg, 'pageLength'), String(table.page.len()))
    }

    const qs = params.toString().replaceAll('%5B', '[').replaceAll('%5D', ']')
    const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname
    window.history.replaceState(null, '', url + window.location.hash)
}
