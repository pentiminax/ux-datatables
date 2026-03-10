import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMercureSubscription } from '../src/functions/mercureSubscription'

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
    const instances: MockEventSource[] = []

    globalThis.EventSource = class extends MockEventSource {
      constructor(url: string, options?: EventSourceInit) {
        super(url, options)
        instances.push(this)
      }
    } as typeof EventSource

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
    expect(instances[0].withCredentials).toBe(false)
  })
})

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
