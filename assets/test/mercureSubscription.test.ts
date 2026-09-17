import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMercureSubscription, toUrlPattern } from '../src/functions/mercureSubscription'

describe('createMercureSubscription', () => {
  const originalEventSource = globalThis.EventSource
  const originalWindow = globalThis.window

  beforeEach(() => {
    globalThis.window = {
      location: {
        href: 'https://example.org/admin',
      },
    } as Window & typeof globalThis
  })

  afterEach(() => {
    globalThis.EventSource = originalEventSource
    globalThis.window = originalWindow
    vi.restoreAllMocks()
  })

  it('appends every configured topic to the Mercure URL', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}', '/api/authors/{id}'],
      },
      vi.fn()
    )

    expect(instances).toHaveLength(1)

    const url = new URL(instances[0].url)

    expect(url.origin + url.pathname).toBe('https://example.org/.well-known/mercure')
    expect(url.searchParams.getAll('topic')).toEqual(['/api/books/{id}', '/api/authors/{id}'])
    expect(url.searchParams.getAll('match')).toEqual([])
    expect(url.searchParams.getAll('match_urlpattern')).toEqual([])
    expect(instances[0].withCredentials).toBe(false)
  })

  it('keeps the legacy topic parameter when the hub runs the legacy protocol', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}'],
        protocolVersion: '0.x',
      },
      vi.fn()
    )

    expect(new URL(instances[0].url).searchParams.getAll('topic')).toEqual(['/api/books/{id}'])
  })

  it('treats an empty protocol version as the legacy protocol', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}'],
        protocolVersion: '',
      },
      vi.fn()
    )

    expect(new URL(instances[0].url).searchParams.getAll('topic')).toEqual(['/api/books/{id}'])
  })

  it('subscribes with URL Pattern matchers when the hub runs the Mercure 1.0 protocol', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}', '/api/authors/42'],
        protocolVersion: '1.0',
      },
      vi.fn()
    )

    const url = new URL(instances[0].url)

    expect(url.searchParams.getAll('topic')).toEqual([])
    expect(url.searchParams.getAll('match_urlpattern')).toEqual(['/api/books/:p0'])
    expect(url.searchParams.getAll('match')).toEqual(['/api/authors/42'])
  })

  it('uses matchers for protocol versions newer than 1.0 as well', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}'],
        protocolVersion: '1.1',
      },
      vi.fn()
    )

    expect(new URL(instances[0].url).searchParams.getAll('match_urlpattern')).toEqual(['/api/books/:p0'])
  })

  it('falls back to an exact matcher for templates a URL Pattern cannot express', () => {
    const instances = mockEventSource()

    createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books{?page}', '/api/books/{id}{?page}'],
        protocolVersion: '1.0',
      },
      vi.fn()
    )

    const url = new URL(instances[0].url)

    expect(url.searchParams.getAll('match_urlpattern')).toEqual([])
    expect(url.searchParams.getAll('match')).toEqual(['/api/books{?page}', '/api/books/{id}{?page}'])
  })

  it('forwards credentials and the debounce delay to the subscription', () => {
    const instances = mockEventSource()
    const onMessage = vi.fn()

    const eventSource = createMercureSubscription(
      {
        hubUrl: '/.well-known/mercure',
        topics: ['/api/books/{id}'],
        withCredentials: true,
        debounceMs: 250,
      },
      onMessage
    )

    expect(instances[0].withCredentials).toBe(true)

    vi.useFakeTimers()

    eventSource.onmessage?.({ data: 'first' } as MessageEvent)
    eventSource.onmessage?.({ data: 'second' } as MessageEvent)

    expect(onMessage).not.toHaveBeenCalled()

    vi.advanceTimersByTime(250)

    expect(onMessage).toHaveBeenCalledTimes(1)
    expect(onMessage.mock.calls[0][0].data).toBe('second')

    vi.useRealTimers()
  })
})

describe('toUrlPattern', () => {
  it('rewrites simple variable expressions into URL Pattern groups', () => {
    expect(toUrlPattern('/api/books/{id}')).toBe('/api/books/:p0')
    expect(toUrlPattern('/api/books/{book_id}/authors/{authorId}')).toBe('/api/books/:p0/authors/:p1')
  })

  it('handles variable names a URL Pattern group name cannot express', () => {
    // RFC 6570: varname = varchar *( ["."] varchar ), varchar = ALPHA / DIGIT / "_" / pct-encoded,
    // so dotted and digit-leading names are valid — `:book.id` is not a group name and `:1` throws.
    expect(toUrlPattern('/api/books/{book.id}')).toBe('/api/books/:p0')
    expect(toUrlPattern('/api/books/{1}')).toBe('/api/books/:p0')
    expect(toUrlPattern('/api/books/{a.b.c}')).toBe('/api/books/:p0')
  })

  it('gives a repeated variable its own group name', () => {
    // URL Patterns reject a duplicate group name, so the second `{id}` cannot reuse `p0`.
    expect(toUrlPattern('/api/books/{id}/authors/{id}')).toBe('/api/books/:p0/authors/:p1')
  })

  it('accepts a variable list and the level-4 modifiers', () => {
    expect(toUrlPattern('/api/books/{id,authorId}')).toBe('/api/books/:p0')
    expect(toUrlPattern('/api/books/{id:3}')).toBe('/api/books/:p0')
    expect(toUrlPattern('/api/books/{ids*}')).toBe('/api/books/:p0')
  })

  it('returns null when the topic is not a template', () => {
    expect(toUrlPattern('/api/books/42')).toBeNull()
    expect(toUrlPattern('https://example.com/books')).toBeNull()
    expect(toUrlPattern('/api/books/%7Bid%7D')).toBeNull()
  })

  it('returns null when an expression uses an RFC 6570 operator', () => {
    expect(toUrlPattern('/api/books{?page}')).toBeNull()
    expect(toUrlPattern('/api/books/{+path}')).toBeNull()
    expect(toUrlPattern('/api/books/{/segments}')).toBeNull()
    expect(toUrlPattern('/api/books/{id}{?page}')).toBeNull()
    expect(toUrlPattern('/api/books/{id,}')).toBeNull()
    expect(toUrlPattern('/api/books/{}')).toBeNull()
  })
})

function mockEventSource(): MockEventSource[] {
  const instances: MockEventSource[] = []

  globalThis.EventSource = class extends MockEventSource {
    constructor(url: string, options?: EventSourceInit) {
      super(url, options)
      instances.push(this)
    }
  } as typeof EventSource

  return instances
}

class MockEventSource {
  public readonly url: string
  public readonly withCredentials: boolean
  public onmessage: ((event: MessageEvent) => void) | null = null

  constructor(url: string, options?: EventSourceInit) {
    this.url = url
    this.withCredentials = options?.withCredentials ?? false
  }

  close(): void {}
}
