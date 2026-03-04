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

export interface UrlCustomOptions {
  target?: string
  displayValue?: string
  routeParams?: Record<string, string>
  template?: string
  showExternalIcon?: boolean
}
