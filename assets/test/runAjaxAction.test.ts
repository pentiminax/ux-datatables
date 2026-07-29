import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { runAjaxAction } from '../src/functions/runAjaxAction'

describe('runAjaxAction', () => {
  let button: HTMLButtonElement
  let dispatch: ReturnType<typeof vi.fn>
  let navigate: ReturnType<typeof vi.fn>
  let reload: ReturnType<typeof vi.fn>

  beforeEach(() => {
    button = document.createElement('button')
    dispatch = vi.fn()
    navigate = vi.fn()
    reload = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  const run = (): Promise<void> =>
    runAjaxAction({
      button,
      method: 'POST',
      url: '/books/42/publish',
      token: 'token-value',
      dispatch,
      navigate,
      reload,
    })

  it('posts the csrf token as _token with json and ajax headers', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await run()

    expect(fetchMock).toHaveBeenCalledOnce()
    expect(fetchMock).toHaveBeenCalledWith('/books/42/publish', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ _token: 'token-value' }),
    })
  })

  const redirectingResponse = (target: string): Response => {
    const response = new Response(null, { status: 200 })
    Object.defineProperty(response, 'redirected', { value: true })
    Object.defineProperty(response, 'url', { value: target })

    return response
  }

  it('dispatches action:success and reloads the table', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 200 })))

    await run()

    expect(dispatch).toHaveBeenCalledWith(
      'action:success',
      expect.objectContaining({ url: '/books/42/publish', method: 'POST' })
    )
    expect(reload).toHaveBeenCalledOnce()
    expect(navigate).not.toHaveBeenCalled()
  })

  it('follows a redirect instead of reloading', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(redirectingResponse(`${window.location.origin}/books`))
    )

    await run()

    expect(navigate).toHaveBeenCalledWith(`${window.location.origin}/books`)
    expect(reload).not.toHaveBeenCalled()
  })

  it('refuses to follow a cross-origin redirect', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(redirectingResponse('https://evil.example.com/books'))
    )

    await run()

    expect(navigate).not.toHaveBeenCalled()
    expect(reload).toHaveBeenCalledOnce()
  })

  it('keeps the button locked after a successful request', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 200 })))

    await run()

    expect(button.disabled).toBe(true)
    expect(button.getAttribute('aria-busy')).toBe('true')
  })

  it('dispatches action:error and re-enables the button on a failed response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 403 })))

    await run()

    expect(dispatch).toHaveBeenCalledWith(
      'action:error',
      expect.objectContaining({ url: '/books/42/publish', method: 'POST' })
    )
    expect(reload).not.toHaveBeenCalled()
    expect(navigate).not.toHaveBeenCalled()
    expect(button.disabled).toBe(false)
    expect(button.hasAttribute('aria-busy')).toBe(false)
  })

  it('dispatches action:error when the request throws', async () => {
    const error = new Error('offline')
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(error))

    await run()

    expect(dispatch).toHaveBeenCalledWith('action:error', expect.objectContaining({ error }))
    expect(button.disabled).toBe(false)
  })

  it('locks the button while the request is pending', async () => {
    let resolveFetch: ((response: Response) => void) | undefined
    vi.stubGlobal(
      'fetch',
      vi.fn().mockImplementation(
        () =>
          new Promise<Response>((resolve) => {
            resolveFetch = resolve
          })
      )
    )

    const pending = run()

    expect(button.disabled).toBe(true)
    expect(button.getAttribute('aria-busy')).toBe('true')

    resolveFetch?.(new Response(null, { status: 200 }))
    await pending
  })

  it('ignores a concurrent call while the button is busy', async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    button.setAttribute('aria-busy', 'true')

    await run()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(dispatch).not.toHaveBeenCalled()
  })
})
