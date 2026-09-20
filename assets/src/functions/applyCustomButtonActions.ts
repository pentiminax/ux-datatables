import { buttonActions } from './buttonActionRegistry.js'

// Collection-type buttons (colvis, or any button with `extend: 'collection'`) nest further
// button descriptors under these keys — DataTables merges prefixButtons/postfixButtons into
// `buttons` at init (dataTables.buttons.mjs). A custom action can live at any depth here (e.g.
// a "Restore order" button placed in the Columns dropdown's postfixButtons alongside
// colvisRestore), so resolution must recurse into all three, not just the top-level array.
const NESTED_BUTTON_KEYS = ['buttons', 'postfixButtons', 'prefixButtons'] as const

// Button::custom() serializes only an `action` name (a string) since JS closures aren't
// JSON-serializable from PHP. Before DataTables initializes, swap that name for the real
// callback registered through `buttonActions`, mirroring how row actions resolve behavior by
// name instead of shipping a function.
function resolveButtonDescriptor(button: unknown): unknown {
    if (typeof button !== 'object' || button === null || Array.isArray(button)) {
        return button
    }

    const descriptor = button as Record<string, unknown>
    const hasAction = typeof descriptor.action === 'string'
    const nestedKeys = NESTED_BUTTON_KEYS.filter((key) => Array.isArray(descriptor[key]))

    if (!hasAction && nestedKeys.length === 0) {
        return button
    }

    const resolved: Record<string, unknown> = { ...descriptor }

    if (hasAction) {
        const name = descriptor.action as string
        const action = buttonActions.get(name)

        if (!action) {
            console.error(`No button action registered for "${name}"`)
            resolved.action = () => {}
        } else {
            resolved.action = action
        }
    }

    for (const key of nestedKeys) {
        resolved[key] = (descriptor[key] as unknown[]).map(resolveButtonDescriptor)
    }

    return resolved
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
