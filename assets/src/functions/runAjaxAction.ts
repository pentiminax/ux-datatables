import { createMutationHeaders } from './createMutationHeaders.js'

export interface AjaxActionOptions {
    button: HTMLElement
    method: string
    url: string
    token: string
    dispatch: (name: string, detail: Record<string, unknown>) => void
    navigate: (url: string) => void
    reload: () => void
}

export async function runAjaxAction({
    button,
    method,
    url,
    token,
    dispatch,
    navigate,
    reload,
}: AjaxActionOptions): Promise<void> {
    if (button.getAttribute('aria-busy') === 'true') {
        return
    }

    button.setAttribute('aria-busy', 'true')
    if (button instanceof HTMLButtonElement) {
        button.disabled = true
    }

    try {
        const response = await fetch(url, {
            method,
            headers: createMutationHeaders(),
            body: JSON.stringify({ _token: token }),
        })

        if (!response.ok) {
            dispatch('action:error', { url, method, response })

            return
        }

        dispatch('action:success', { url, method, response })

        if (response.redirected && response.url) {
            navigate(response.url)

            return
        }

        reload()
    } catch (error) {
        dispatch('action:error', { url, method, error })
    } finally {
        button.removeAttribute('aria-busy')
        if (button instanceof HTMLButtonElement) {
            button.disabled = false
        }
    }
}
