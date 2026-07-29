import { createMutationHeaders } from './createMutationHeaders.js';
export async function runAjaxAction({ button, method, url, token, dispatch, navigate, reload, }) {
    if (button.getAttribute('aria-busy') === 'true') {
        return;
    }
    button.setAttribute('aria-busy', 'true');
    if (button instanceof HTMLButtonElement) {
        button.disabled = true;
    }
    try {
        const response = await fetch(url, {
            method,
            headers: createMutationHeaders(),
            body: JSON.stringify({ _token: token }),
        });
        if (!response.ok) {
            dispatch('action:error', { url, method, response });
            return;
        }
        dispatch('action:success', { url, method, response });
        if (response.redirected && response.url) {
            navigate(response.url);
            return;
        }
        reload();
    }
    catch (error) {
        dispatch('action:error', { url, method, error });
    }
    finally {
        button.removeAttribute('aria-busy');
        if (button instanceof HTMLButtonElement) {
            button.disabled = false;
        }
    }
}
//# sourceMappingURL=runAjaxAction.js.map