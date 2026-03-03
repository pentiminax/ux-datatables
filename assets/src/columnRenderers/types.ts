export interface ColumnRenderer {
  matches(column: Record<string, any>): boolean
  configure(column: Record<string, any>): void
}
