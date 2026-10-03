import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    toggle() {
        const root = document.documentElement
        const current =
            root.dataset.theme ??
            (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        const next = current === 'dark' ? 'light' : 'dark'

        root.dataset.theme = next
        try {
            localStorage.setItem('demo-theme', next)
        } catch {}
    }
}
