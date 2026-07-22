export type BadgeVariant =
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'primary'
    | 'secondary'
    | 'light'
    | 'dark'

export interface SwitchRenderOptions {
    checked: boolean
    disabled: boolean
    ariaLabel: string
    dataId: string
    dataUrl: string
    dataField: string
    dataMethod: string
}

export interface ColumnStyleAdapter {
    renderBadge(label: string, variant: string): string
    renderSwitch(options: SwitchRenderOptions): string
    renderIcon(iconSvg: string, variant: string, tooltip: string): string
}

export type ColumnStyleAdapterFactory = () => ColumnStyleAdapter
