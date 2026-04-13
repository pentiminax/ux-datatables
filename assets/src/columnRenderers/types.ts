export interface ColumnRenderer {
    matches(column: Record<string, any>): boolean

    configure(column: Record<string, any>): void
}

export interface BaseColumnData {
    cellType?: string
    className?: string
    data?: string | null
    defaultContent?: string
    name?: string
    orderable?: boolean
    render?: ((data: any, type: string, row: Record<string, any>) => any) | string
    searchable?: boolean
    title?: string
    type?: string
    visible?: boolean
    width?: string
    field?: string
    customOptions?: Record<string, unknown>
}

export interface BooleanCustomOptions {
    renderAsSwitch?: boolean
    defaultState?: boolean
    toggleMethod?: string
    toggleIdField?: string
    entityClass?: string
    toggleField?: string
}

export interface ChoiceCustomOptions {
    choices?: Record<string, string>
    renderAsBadges?: Record<string, string> | boolean
    defaultBadgeVariant?: string
}

export interface EmailCustomOptions {
    isEmail?: boolean
    obfuscate?: boolean
    mask?: boolean
    displayValue?: string
}

export interface UrlCustomOptions {
    target?: string
    displayValue?: string
    routeParams?: Record<string, string>
    template?: string
    showExternalIcon?: boolean
}

export interface ImageCustomOptions {
    isImage?: boolean
    imageWidth?: number
    imageHeight?: number
    alt?: string
    lazy?: boolean
    rounded?: boolean
    placeholder?: string
    clickable?: boolean
}

export interface ActionConfig {
    type: string
    label: string
    className: string
    icon?: string
    confirm?: string
    displayCondition?: { field: string; value: unknown }
    entityClass?: string
    htmlAttributes?: Record<string, string | number | boolean | null>
    idField: string
    url?: string
}

export interface ActionRowConfig {
    url?: string
}

export interface ActionRowData {
    __ux_datatables_actions?: Record<string, ActionRowConfig>
}
