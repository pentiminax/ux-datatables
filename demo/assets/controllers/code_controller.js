import { Controller } from '@hotwired/stimulus'
import hljs from 'highlight.js/lib/core'
import php from 'highlight.js/lib/languages/php'
import twig from 'highlight.js/lib/languages/twig'

hljs.registerLanguage('php', php)
hljs.registerLanguage('twig', twig)

export default class extends Controller {
    static targets = ['tab', 'panel']

    connect() {
        for (const block of this.element.querySelectorAll('pre code')) {
            hljs.highlightElement(block)
        }
    }

    select(event) {
        this.show(this.tabTargets.indexOf(event.currentTarget))
    }

    navigate(event) {
        const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key]
        if (!step) return

        const count = this.tabTargets.length
        const index = (this.tabTargets.indexOf(event.currentTarget) + step + count) % count
        this.show(index)
        this.tabTargets[index].focus()
    }

    show(index) {
        this.tabTargets.forEach((tab, i) => {
            tab.setAttribute('aria-selected', String(i === index))
            tab.tabIndex = i === index ? 0 : -1
        })
        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index
        })
    }

    async copy(event) {
        const button = event.currentTarget
        const visible = this.panelTargets.find((panel) => !panel.hidden)
        const text = button.dataset.copyText ?? visible?.textContent ?? ''

        try {
            await navigator.clipboard.writeText(text)
        } catch {
            return
        }

        const label = button.textContent
        button.textContent = button.dataset.copiedLabel
        setTimeout(() => {
            button.textContent = label
        }, 1600)
    }
}
