import { buttonActions } from './buttonActionRegistry.js'

// Button::custom() serializes only an `action` name (a string) since JS closures aren't
// JSON-serializable from PHP. Before DataTables initializes, swap that name for the real
// callback registered through `buttonActions`, mirroring how row actions resolve behavior by
// name instead of shipping a function.
function resolveButtonDescriptor(button: unknown): unknown {
    if (typeof button !== 'object' || button === null || Array.isArray(button)) {
        return button
    }

    const descriptor = button as Record<string, unknown>

    if (typeof descriptor.action !== 'string') {
        return button
    }

    const action = buttonActions.get(descriptor.action)

    if (!action) {
        console.error(`No button action registered for "${descriptor.action}"`)

        return { ...descriptor, action: () => {} }
    }

    return { ...descriptor, action }
}

function resolveButtonsArray(buttons: unknown): unknown {
    return Array.isArray(buttons) ? buttons.map(resolveButtonDescriptor) : buttons
}

function resolveLayoutValue(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map(resolveLayoutValue)
    }

    if (typeof value !== 'object' || value === null) {
        return value
    }

    const record = value as Record<string, unknown>

    return 'buttons' in record ? { ...record, buttons: resolveButtonsArray(record.buttons) } : value
}

export function applyCustomButtonActions(payload: Record<string, unknown>): void {
    const layout = payload.layout

    if (typeof layout !== 'object' || layout === null || Array.isArray(layout)) {
        return
    }

    const record = layout as Record<string, unknown>

    for (const position of Object.keys(record)) {
        record[position] = resolveLayoutValue(record[position])
    }
}
