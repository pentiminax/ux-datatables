export function createModalRoot(html: string): HTMLElement | null {
    const template = document.createElement('template')
    template.innerHTML = html.trim()

    const modalRoot = template.content.querySelector<HTMLElement>('[data-ux-datatables-modal]')

    if (!modalRoot) {
        console.error(
            '[ux-datatables] Edit modal template must include [data-ux-datatables-modal].'
        )

        return null
    }

    return modalRoot
}

export function extractFormData(form: HTMLFormElement): Record<string, unknown> {
    const data: Record<string, unknown> = {}
    const formData = new FormData(form)

    const firstInput = form.querySelector<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >('input:not([type=hidden]), select, textarea')

    const nameMatch = firstInput?.name?.match(/^([^[]+)\[/)
    const prefix = nameMatch ? nameMatch[1] : null

    formData.forEach((value, key) => {
        let normalizedKey = key

        if (prefix) {
            const fieldMatch = key.match(new RegExp(`^${prefix}\\[([^\\]]+)\\]$`))

            if (fieldMatch) {
                normalizedKey = fieldMatch[1]
            }
        }

        const currentValue = data[normalizedKey]

        if (undefined === currentValue) {
            data[normalizedKey] = value

            return
        }

        if (Array.isArray(currentValue)) {
            currentValue.push(value)

            return
        }

        data[normalizedKey] = [currentValue, value]
    })

    return data
}
