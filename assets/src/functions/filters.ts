import type { StyleFramework } from '../types/styleFramework.js'

export type FilterType = 'text' | 'select' | 'ternary' | 'dateRange' | 'checkbox'

export interface FilterDefinition {
    name: string
    type: FilterType
    label?: string
    placeholder?: string
    options?: Record<string, string>
    multiple?: boolean
    trueLabel?: string
    falseLabel?: string
}

export type FilterValue = string | string[] | { from?: string; to?: string }

/**
 * Optional overrides for the filter bar chrome strings. Sourced from the PHP
 * `Filters::labels()` config (already translated server-side) via
 * `payload.filterLabels`. Each falls back to a built-in English default.
 */
export interface FilterBarLabels {
    title?: string
    reset?: string
    apply?: string
    all?: string
}

interface FilterControl {
    definition: FilterDefinition
    getValue: () => FilterValue | null
    reset: () => void
}

const BOOTSTRAP_FRAMEWORKS: StyleFramework[] = ['bs', 'bs4', 'bs5']

function isBootstrap(framework: StyleFramework): boolean {
    return BOOTSTRAP_FRAMEWORKS.includes(framework)
}

function inputClass(framework: StyleFramework): string {
    return isBootstrap(framework) ? 'form-control' : 'dt-filter-input'
}

function selectClass(framework: StyleFramework): string {
    return isBootstrap(framework) ? 'form-select' : 'dt-filter-input'
}

export function hasFilters(payload: Record<string, any>): boolean {
    return Array.isArray(payload?.filters) && payload.filters.length > 0
}

function normalizeValue(value: FilterValue | null): FilterValue | null {
    if (value === null) return null
    if (typeof value === 'string') return value.trim() === '' ? null : value
    if (Array.isArray(value)) return value.length === 0 ? null : value

    const from = value.from?.trim() ? value.from : undefined
    const to = value.to?.trim() ? value.to : undefined
    if (from === undefined && to === undefined) return null

    const range: { from?: string; to?: string } = {}
    if (from !== undefined) range.from = from
    if (to !== undefined) range.to = to
    return range
}

const FUNNEL_ICON =
    '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" ' +
    'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M3 4h14l-5.5 6.5V16l-3 1.5v-7L3 4z" /></svg>'

export class FilterBar {
    private readonly definitions: FilterDefinition[]
    private readonly labels: FilterBarLabels
    private readonly controls: FilterControl[] = []
    private readonly wrapper: HTMLDivElement
    private readonly popover: HTMLDivElement
    private readonly toggle: HTMLButtonElement
    private readonly badge: HTMLSpanElement
    private applied: Record<string, FilterValue> = {}
    private reload: () => void = () => {}
    private documentClickHandler: ((event: MouseEvent) => void) | null = null

    constructor(
        payload: Record<string, any>,
        private readonly framework: StyleFramework
    ) {
        this.definitions = (payload.filters as FilterDefinition[]) ?? []
        this.labels = (payload.filterLabels as FilterBarLabels) ?? {}

        this.wrapper = document.createElement('div')
        this.wrapper.className = 'dt-filters'

        this.toggle = document.createElement('button')
        this.toggle.type = 'button'
        this.toggle.className = 'dt-filters-toggle'
        this.toggle.setAttribute('aria-expanded', 'false')
        this.toggle.setAttribute('aria-label', this.labels.title ?? 'Filters')
        this.toggle.innerHTML = FUNNEL_ICON

        this.badge = document.createElement('span')
        this.badge.className = 'dt-filters-badge'
        this.badge.textContent = '0'
        this.toggle.appendChild(this.badge)

        this.popover = document.createElement('div')
        this.popover.className = 'dt-filters-popover'
        this.popover.hidden = true

        this.wrapper.appendChild(this.toggle)
        this.wrapper.appendChild(this.popover)
    }

    /**
     * Merge the last applied filter values into the table's AJAX request data,
     * preserving any pre-existing static `ajax.data` object.
     */
    attachToPayload(payload: Record<string, any>): void {
        if (!payload.ajax || typeof payload.ajax !== 'object') {
            return
        }

        const existing = payload.ajax.data
        payload.ajax.data = (data: Record<string, any>) => {
            if (existing && typeof existing === 'object' && !Array.isArray(existing)) {
                Object.assign(data, existing)
            }
            data.filters = this.collectValues()
            return data
        }
    }

    /** The applied snapshot — what server-side requests should use. */
    collectValues(): Record<string, FilterValue> {
        return this.applied
    }

    /** Live values currently entered in the controls (not yet applied). */
    private snapshot(): Record<string, FilterValue> {
        const out: Record<string, FilterValue> = {}
        for (const control of this.controls) {
            const value = normalizeValue(control.getValue())
            if (value !== null) {
                out[control.definition.name] = value
            }
        }
        return out
    }

    /**
     * Build the popover contents, wire the apply/reset/toggle behaviour, and
     * return the wrapper. `reload` is invoked whenever applied filters change so
     * the table can refresh (typically `() => api.ajax.reload(null, true)`).
     */
    render(reload: () => void): HTMLElement {
        this.reload = reload

        this.popover.appendChild(this.buildHeader())

        const body = document.createElement('div')
        body.className = 'dt-filters-popover__body'
        for (const definition of this.definitions) {
            const { wrapper, control } = this.buildControl(definition)
            this.controls.push(control)
            body.appendChild(wrapper)
        }
        this.popover.appendChild(body)
        this.popover.appendChild(this.buildFooter())

        this.toggle.addEventListener('click', () => this.togglePopover())

        return this.wrapper
    }

    private buildHeader(): HTMLElement {
        const header = document.createElement('div')
        header.className = 'dt-filters-popover__header'

        const title = document.createElement('span')
        title.className = 'dt-filters-popover__title'
        title.textContent = this.labels.title ?? 'Filters'

        const reset = document.createElement('button')
        reset.type = 'button'
        reset.className = 'dt-filters-reset'
        reset.textContent = this.labels.reset ?? 'Reset'
        reset.addEventListener('click', () => this.resetFilters())

        header.appendChild(title)
        header.appendChild(reset)
        return header
    }

    private buildFooter(): HTMLElement {
        const footer = document.createElement('div')
        footer.className = 'dt-filters-popover__footer'

        const apply = document.createElement('button')
        apply.type = 'button'
        apply.className = 'dt-filters-apply'
        apply.textContent = this.labels.apply ?? 'Apply filters'
        apply.addEventListener('click', () => this.applyFilters())

        footer.appendChild(apply)
        return footer
    }

    private applyFilters(): void {
        this.applied = this.snapshot()
        this.updateBadge()
        this.closePopover()
        this.reload()
    }

    private resetFilters(): void {
        for (const control of this.controls) {
            control.reset()
        }
        this.applied = {}
        this.updateBadge()
        this.reload()
    }

    private updateBadge(): void {
        const count = Object.keys(this.applied).length
        this.badge.textContent = String(count)
        this.toggle.classList.toggle('dt-filters-toggle--active', count > 0)
    }

    private togglePopover(): void {
        if (this.popover.hidden) {
            this.openPopover()
        } else {
            this.closePopover()
        }
    }

    private openPopover(): void {
        this.popover.hidden = false
        this.toggle.setAttribute('aria-expanded', 'true')

        this.documentClickHandler = (event: MouseEvent) => {
            if (!this.wrapper.contains(event.target as Node)) {
                this.closePopover()
            }
        }
        document.addEventListener('mousedown', this.documentClickHandler)
    }

    private closePopover(): void {
        this.popover.hidden = true
        this.toggle.setAttribute('aria-expanded', 'false')

        if (this.documentClickHandler) {
            document.removeEventListener('mousedown', this.documentClickHandler)
            this.documentClickHandler = null
        }
    }

    private buildControl(definition: FilterDefinition): {
        wrapper: HTMLElement
        control: FilterControl
    } {
        const wrapper = document.createElement('div')
        wrapper.className = 'dt-filter'

        if (definition.type === 'checkbox') {
            return { wrapper, control: this.buildCheckbox(definition, wrapper) }
        }

        const label = document.createElement('label')
        label.className = isBootstrap(this.framework) ? 'form-label' : 'dt-filter-label'
        label.textContent = definition.label ?? definition.name
        wrapper.appendChild(label)

        const control = this.buildField(definition, wrapper)
        return { wrapper, control }
    }

    private buildField(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        switch (definition.type) {
            case 'select':
                return this.buildSelect(definition, wrapper)
            case 'ternary':
                return this.buildTernary(definition, wrapper)
            case 'dateRange':
                return this.buildDateRange(definition, wrapper)
            default:
                return this.buildText(definition, wrapper)
        }
    }

    private buildText(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        const input = document.createElement('input')
        input.type = 'search'
        input.className = inputClass(this.framework)
        input.name = `filters[${definition.name}]`
        if (definition.placeholder) input.placeholder = definition.placeholder
        wrapper.appendChild(input)

        return {
            definition,
            getValue: () => input.value,
            reset: () => {
                input.value = ''
            },
        }
    }

    private buildSelect(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        const select = document.createElement('select')
        select.className = selectClass(this.framework)
        select.name = `filters[${definition.name}]`
        select.multiple = definition.multiple === true

        if (!select.multiple) {
            const empty = document.createElement('option')
            empty.value = ''
            empty.textContent = definition.placeholder ?? this.labels.all ?? 'All'
            select.appendChild(empty)
        }

        for (const [value, optLabel] of Object.entries(definition.options ?? {})) {
            const option = document.createElement('option')
            option.value = value
            option.textContent = optLabel
            select.appendChild(option)
        }

        wrapper.appendChild(select)

        return {
            definition,
            getValue: () =>
                select.multiple
                    ? [...select.selectedOptions].map((o) => o.value).filter((v) => v !== '')
                    : select.value,
            reset: () => {
                select.selectedIndex = select.multiple ? -1 : 0
            },
        }
    }

    private buildTernary(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        const select = document.createElement('select')
        select.className = selectClass(this.framework)
        select.name = `filters[${definition.name}]`

        const optionsMap: Array<[string, string]> = [
            ['', definition.placeholder ?? this.labels.all ?? 'All'],
            ['true', definition.trueLabel ?? 'Yes'],
            ['false', definition.falseLabel ?? 'No'],
        ]

        for (const [value, optLabel] of optionsMap) {
            const option = document.createElement('option')
            option.value = value
            option.textContent = optLabel
            select.appendChild(option)
        }

        wrapper.appendChild(select)

        return {
            definition,
            getValue: () => select.value,
            reset: () => {
                select.selectedIndex = 0
            },
        }
    }

    private buildDateRange(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        const group = document.createElement('div')
        group.className = isBootstrap(this.framework) ? 'd-flex gap-1' : 'dt-filter-range'

        const from = document.createElement('input')
        from.type = 'date'
        from.className = inputClass(this.framework)
        from.name = `filters[${definition.name}][from]`

        const to = document.createElement('input')
        to.type = 'date'
        to.className = inputClass(this.framework)
        to.name = `filters[${definition.name}][to]`

        group.appendChild(from)
        group.appendChild(to)
        wrapper.appendChild(group)

        return {
            definition,
            getValue: () => ({ from: from.value, to: to.value }),
            reset: () => {
                from.value = ''
                to.value = ''
            },
        }
    }

    private buildCheckbox(definition: FilterDefinition, wrapper: HTMLElement): FilterControl {
        wrapper.classList.add('dt-filter--checkbox')

        const label = document.createElement('label')
        label.className = 'dt-filter-checkbox-label'

        const input = document.createElement('input')
        input.type = 'checkbox'
        input.className = isBootstrap(this.framework) ? 'form-check-input' : 'dt-filter-checkbox'
        input.name = `filters[${definition.name}]`
        input.value = '1'

        const text = document.createElement('span')
        text.textContent = definition.label ?? definition.name

        label.appendChild(input)
        label.appendChild(text)
        wrapper.appendChild(label)

        return {
            definition,
            getValue: () => (input.checked ? '1' : ''),
            reset: () => {
                input.checked = false
            },
        }
    }
}
