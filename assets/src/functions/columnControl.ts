type ColumnControlTarget = number | string

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function resolveTarget(value: unknown): ColumnControlTarget | null {
    if (typeof value === 'string') {
        return value
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return value
    }

    return null
}

function extractTargets(columnControl: unknown): ColumnControlTarget[] {
    if (Array.isArray(columnControl)) {
        if (columnControl.length === 0) {
            return []
        }

        const targets = columnControl.map((item) => {
            if (isRecord(item)) {
                return resolveTarget(item.target) ?? 0
            }

            return 0
        })

        return [...new Set(targets)]
    }

    if (isRecord(columnControl)) {
        return [resolveTarget(columnControl.target) ?? 0]
    }

    return []
}

export function normalizeDisabledColumnControls(payload: Record<string, unknown>): void {
    if (!Array.isArray(payload.columns)) {
        return
    }

    const targets = extractTargets(payload.columnControl)

    if (targets.length === 0) {
        return
    }

    for (const column of payload.columns) {
        if (!isRecord(column) || !Array.isArray(column.columnControl)) {
            continue
        }

        if (column.columnControl.length === 0) {
            column.columnControl = targets.map((target) => ({
                target,
                content: [],
            }))
        }
    }
}
