import type { ColumnStyleAdapter } from '../columnStyles/ColumnStyleAdapter.js'
import { parseBooleanValue } from '../functions/htmlUtils.js'
import type { ColumnRenderer, IconCustomOptions } from './types.js'

type IconNode = unknown
interface LucideModule {
    icons: Record<string, IconNode>
    createElement: (iconNode: IconNode, attrs?: Record<string, unknown>) => SVGElement
}

const SIZE_PX: Record<string, number> = { xs: 12, sm: 16, md: 20, lg: 24, xl: 32 }

// Lazily loaded once, then read synchronously by DataTables' render callback.
let lucide: LucideModule | null = null

export async function loadLucideIcons(): Promise<void> {
    if (lucide === null) {
        lucide = (await import('lucide')) as unknown as LucideModule
    }
}

function kebabToPascal(name: string): string {
    return name
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join('')
}

function renderSvg(iconName: string, sizePx: number): string | null {
    if (lucide === null || iconName.length === 0) {
        return null
    }

    const iconNode = lucide.icons[kebabToPascal(iconName)]
    if (iconNode === undefined) {
        return null
    }

    return lucide.createElement(iconNode, { width: sizePx, height: sizePx }).outerHTML
}

export function createIconColumnRenderer(style: ColumnStyleAdapter): ColumnRenderer {
    return {
        matches(column: Record<string, any>): boolean {
            return true === column?.customOptions?.isIcon
        },

        configure(column: Record<string, any>): void {
            const customOptions = (column.customOptions ?? {}) as IconCustomOptions
            const columnKey = column.data ?? column.name
            const tooltips = customOptions.tooltips ?? {}
            const staticIcon = customOptions.icon ?? ''
            const staticColor = customOptions.color ?? ''
            const sizePx = SIZE_PX[customOptions.size ?? 'md'] ?? SIZE_PX.md
            const booleanMode = true === customOptions.boolean

            column.render = (data: any, type: string, row: any): any => {
                if (type !== 'display') {
                    return data
                }

                let iconName: string
                let variant: string
                let tooltip: string

                if (booleanMode) {
                    const on = parseBooleanValue(data)
                    iconName = (on ? customOptions.trueIcon : customOptions.falseIcon) ?? staticIcon
                    variant =
                        (on ? customOptions.trueColor : customOptions.falseColor) ?? staticColor
                    tooltip = ''
                } else {
                    const resolved = row?.__ux_datatables_icons?.[columnKey]
                    iconName = resolved?.icon ?? staticIcon
                    variant = resolved?.color ?? staticColor
                    tooltip = tooltips[String(data ?? '')] ?? ''
                }

                const svg = renderSvg(iconName, sizePx)
                if (svg === null) {
                    return ''
                }

                return style.renderIcon(svg, variant, tooltip)
            }
        },
    }
}
