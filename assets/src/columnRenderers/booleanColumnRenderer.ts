import type { ColumnStyleAdapter } from '../columnStyles/ColumnStyleAdapter.js'
import { escapeHtml, parseBooleanValue } from '../functions/htmlUtils.js'
import type { BooleanCustomOptions, BooleanSwitchRowData, ColumnRenderer } from './types.js'

type BooleanSwitchRow = BooleanSwitchRowData & Record<string, unknown>

export function createBooleanColumnRenderer(
    toggleUrl: string,
    mutationsEnabled = true,
    style: ColumnStyleAdapter
): ColumnRenderer {
    return {
        matches(column: Record<string, any>): boolean {
            return true === column?.customOptions?.renderAsSwitch
        },

        configure(column: Record<string, any>): void {
            const customOptions = (column.customOptions ?? {}) as BooleanCustomOptions
            const defaultState = true === customOptions.defaultState
            const toggleMethod = customOptions.toggleMethod ?? 'PATCH'
            const toggleIdField = customOptions.toggleIdField ?? 'id'
            const effectiveField =
                [customOptions.toggleField, column.field, column.data, column.name].find(
                    (field): field is string => typeof field === 'string' && field.length > 0
                ) ?? ''

            column.type ??= 'num'

            column.render = (
                data: unknown,
                type: string,
                row: BooleanSwitchRow
            ): string | number => {
                const boolValue = parseBooleanValue(data, defaultState)

                if (type === 'sort' || type === 'type') {
                    return boolValue ? 1 : 0
                }

                if (type === 'filter') {
                    return boolValue ? 'ON' : 'OFF'
                }

                if (type !== 'display') {
                    return boolValue ? 'ON' : 'OFF'
                }

                const metadataId = row?.__ux_datatables_boolean_switches?.[effectiveField]
                const rowId =
                    metadataId !== null && metadataId !== undefined && metadataId !== ''
                        ? metadataId
                        : row?.[toggleIdField]
                const disabled =
                    !mutationsEnabled || rowId === null || rowId === undefined || rowId === ''

                return style.renderSwitch({
                    checked: boolValue,
                    disabled,
                    ariaLabel: boolValue ? 'ON' : 'OFF',
                    dataId: escapeHtml(String(rowId ?? '')),
                    dataUrl: escapeHtml(toggleUrl),
                    dataField: escapeHtml(effectiveField),
                    dataMethod: escapeHtml(toggleMethod.toUpperCase()),
                })
            }
        },
    }
}
