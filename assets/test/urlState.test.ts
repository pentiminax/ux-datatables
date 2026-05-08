import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
    applyUrlStateToPayload,
    isUrlStateEnabled,
    readUrlState,
    writeUrlState,
    type UrlStateConfig,
} from '../src/functions/urlState'

const baseCfg: UrlStateConfig = { search: true, order: true, page: true, pageLength: true, prefix: '' }
const prefixedCfg: UrlStateConfig = { search: true, order: true, page: true, pageLength: true, prefix: 'users' }

describe('isUrlStateEnabled', () => {
    it('returns null when urlState absent', () => {
        expect(isUrlStateEnabled({})).toBeNull()
    })

    it('returns null when all keys false', () => {
        expect(
            isUrlStateEnabled({ urlState: { search: false, order: false, page: false, pageLength: false } })
        ).toBeNull()
    })

    it('returns config when at least one key true', () => {
        const result = isUrlStateEnabled({
            urlState: { search: true, order: false, page: false, pageLength: false, prefix: '' },
        })
        expect(result).toEqual({ search: true, order: false, page: false, pageLength: false, prefix: '' })
    })

    it('returns config when all keys explicitly true', () => {
        const result = isUrlStateEnabled({
            urlState: { search: true, order: true, page: true, pageLength: true, prefix: 'x' },
        })
        expect(result).toEqual({ search: true, order: true, page: true, pageLength: true, prefix: 'x' })
    })

    it('defaults missing prefix to empty string', () => {
        const result = isUrlStateEnabled({ urlState: { search: true } })
        expect(result?.prefix).toBe('')
    })
})

describe('readUrlState — no prefix', () => {
    it('reads all keys from flat query string (single-column order)', () => {
        const snap = readUrlState(
            baseCfg,
            '?search=foo&order%5Bname%5D=id&order%5Bdir%5D=asc&start=20&pageLength=25'
        )
        expect(snap.search).toBe('foo')
        expect(snap.order).toEqual({ name: 'id', dir: 'asc' })
        expect(snap.start).toBe(20)
        expect(snap.pageLength).toBe(25)
    })

    it('reads multi-column order', () => {
        const snap = readUrlState(
            baseCfg,
            '?order%5B0%5D%5Bname%5D=id&order%5B0%5D%5Bdir%5D=asc&order%5B1%5D%5Bname%5D=email&order%5B1%5D%5Bdir%5D=desc'
        )
        expect(snap.order).toEqual([
            { name: 'id', dir: 'asc' },
            { name: 'email', dir: 'desc' },
        ])
    })

    it('ignores invalid dir values', () => {
        const snap = readUrlState(baseCfg, '?order%5Bname%5D=id&order%5Bdir%5D=invalid')
        expect(snap.order).toBeUndefined()
    })

    it('ignores keys not enabled', () => {
        const cfg: UrlStateConfig = { search: false, order: true, page: false, pageLength: false, prefix: '' }
        const snap = readUrlState(
            cfg,
            '?search=foo&order%5Bname%5D=id&order%5Bdir%5D=asc&start=20&pageLength=25'
        )
        expect(snap.search).toBeUndefined()
        expect(snap.order).toEqual({ name: 'id', dir: 'asc' })
        expect(snap.start).toBeUndefined()
        expect(snap.pageLength).toBeUndefined()
    })

    it('ignores negative start', () => {
        const snap = readUrlState(baseCfg, '?start=-5')
        expect(snap.start).toBeUndefined()
    })

    it('ignores non-numeric pageLength', () => {
        const snap = readUrlState(baseCfg, '?pageLength=abc')
        expect(snap.pageLength).toBeUndefined()
    })

    it('returns empty snapshot when query string empty', () => {
        expect(readUrlState(baseCfg, '')).toEqual({})
    })
})

describe('readUrlState — with prefix', () => {
    it('reads bracket-notation keys', () => {
        const snap = readUrlState(
            prefixedCfg,
            '?users%5Bsearch%5D=bar&users%5Border%5D%5Bname%5D=id&users%5Border%5D%5Bdir%5D=desc&users%5Bstart%5D=10&users%5BpageLength%5D=50'
        )
        expect(snap.search).toBe('bar')
        expect(snap.order).toEqual({ name: 'id', dir: 'desc' })
        expect(snap.start).toBe(10)
        expect(snap.pageLength).toBe(50)
    })

    it('does not read flat keys when prefix set', () => {
        const snap = readUrlState(prefixedCfg, '?search=foo&order%5Bname%5D=id&order%5Bdir%5D=asc')
        expect(snap.search).toBeUndefined()
        expect(snap.order).toBeUndefined()
    })
})

describe('applyUrlStateToPayload', () => {
    it('sets search as object when payload.search absent', () => {
        const payload: Record<string, any> = {}
        applyUrlStateToPayload(payload, { search: 'foo' })
        expect(payload.search).toEqual({ search: 'foo' })
    })

    it('merges into existing search object, preserving other keys', () => {
        const payload: Record<string, any> = { search: { search: '', regex: false } }
        applyUrlStateToPayload(payload, { search: 'bar' })
        expect(payload.search).toEqual({ search: 'bar', regex: false })
    })

    it('sets order', () => {
        const payload: Record<string, any> = {}
        applyUrlStateToPayload(payload, { order: { name: 'id', dir: 'asc' } })
        expect(payload.order).toEqual({ name: 'id', dir: 'asc' })
    })

    it('sets displayStart', () => {
        const payload: Record<string, any> = {}
        applyUrlStateToPayload(payload, { start: 20 })
        expect(payload.displayStart).toBe(20)
    })

    it('sets pageLength', () => {
        const payload: Record<string, any> = {}
        applyUrlStateToPayload(payload, { pageLength: 25 })
        expect(payload.pageLength).toBe(25)
    })

    it('does nothing when snapshot empty', () => {
        const payload: Record<string, any> = { order: [[0, 'asc']] }
        applyUrlStateToPayload(payload, {})
        expect(payload.order).toEqual([[0, 'asc']])
    })
})

describe('writeUrlState', () => {
    const originalWindow = globalThis.window
    let replaceStateSpy: ReturnType<typeof vi.fn>

    function makeTable(
        search = '',
        order: Array<[number, string]> = [[0, 'asc']],
        start = 0,
        pageLen = 10,
        columnNames: string[] = ['id', 'email']
    ) {
        return {
            search: () => search,
            order: () => order,
            column: (idx: number) => ({ name: () => columnNames[idx] ?? String(idx) }),
            page: {
                info: () => ({ start }),
                len: () => pageLen,
            },
        }
    }

    beforeEach(() => {
        replaceStateSpy = vi.fn()
        globalThis.window = {
            location: { pathname: '/users', search: '', hash: '' },
            history: { replaceState: replaceStateSpy },
        } as unknown as Window & typeof globalThis
    })

    afterEach(() => {
        globalThis.window = originalWindow
        vi.restoreAllMocks()
    })

    it('writes search to URL', () => {
        writeUrlState(baseCfg, makeTable('foo'))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('search=foo')
    })

    it('writes single-column order as order[name] + order[dir] unencoded', () => {
        writeUrlState(baseCfg, makeTable('', [[0, 'desc']]))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('order[name]=id')
        expect(url).toContain('order[dir]=desc')
        expect(url).not.toContain('%5B')
    })

    it('writes multi-column order as indexed entries', () => {
        writeUrlState(baseCfg, makeTable('', [[0, 'asc'], [1, 'desc']]))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('order[0][name]=id')
        expect(url).toContain('order[0][dir]=asc')
        expect(url).toContain('order[1][name]=email')
        expect(url).toContain('order[1][dir]=desc')
    })

    it('writes bracket-notation keys when prefix set, unencoded', () => {
        writeUrlState(prefixedCfg, makeTable('alice', [[0, 'desc']], 20, 25))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('users[search]=alice')
        expect(url).toContain('users[order][name]=id')
        expect(url).toContain('users[order][dir]=desc')
        expect(url).toContain('users[start]=20')
        expect(url).toContain('users[pageLength]=25')
        expect(url).not.toContain('%5B')
    })

    it('writes pageLength param', () => {
        writeUrlState(baseCfg, makeTable('', [[0, 'asc']], 0, 25))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('pageLength=25')
    })

    it('omits start=0 from URL', () => {
        writeUrlState(baseCfg, makeTable('', [[0, 'asc']], 0))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).not.toContain('start')
    })

    it('omits empty search from URL', () => {
        writeUrlState(baseCfg, makeTable('', [[0, 'asc']], 0))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).not.toContain('search=')
    })

    it('produces bare pathname when pageLength disabled and no other state', () => {
        const cfg: UrlStateConfig = { search: true, order: true, page: true, pageLength: false, prefix: '' }
        writeUrlState(cfg, makeTable('', [], 0))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toBe('/users')
    })

    it('preserves hash', () => {
        ;(globalThis.window.location as any).hash = '#section'
        writeUrlState(baseCfg, makeTable('x'))
        const url = replaceStateSpy.mock.calls[0][2] as string
        expect(url).toContain('#section')
    })
})
