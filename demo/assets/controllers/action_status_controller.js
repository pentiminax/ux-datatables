import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['message']
    static values = { success: String, failed: String, forbidden: String }

    connect() {
        this.element.addEventListener('datatables:action:success', this.onSuccess)
        this.element.addEventListener('datatables:action:error', this.onError)
    }

    disconnect() {
        this.element.removeEventListener('datatables:action:success', this.onSuccess)
        this.element.removeEventListener('datatables:action:error', this.onError)
    }

    onSuccess = ({ detail }) => this.show(this.successValue, detail)

    onError = (event) => {
        // The bundle announces a failure itself unless the event is canceled; this page shows it instead.
        event.preventDefault()
        this.show(event.detail.response?.status === 403 ? this.forbiddenValue : this.failedValue, event.detail)
    }

    show(template, { actionType, id }) {
        this.messageTarget.textContent = template.replace('{type}', actionType).replace('{id}', id)
        this.messageTarget.hidden = false
    }
}
