import { buttonActions } from './buttonActionRegistry.js';
const NESTED_BUTTON_KEYS = ['buttons', 'postfixButtons', 'prefixButtons'];
function resolveButtonDescriptor(button) {
    if (typeof button !== 'object' || button === null || Array.isArray(button)) {
        return button;
    }
    const descriptor = button;
    const hasAction = typeof descriptor.action === 'string';
    const nestedKeys = NESTED_BUTTON_KEYS.filter((key) => Array.isArray(descriptor[key]));
    if (!hasAction && nestedKeys.length === 0) {
        return button;
    }
    const resolved = { ...descriptor };
    if (hasAction) {
        const name = descriptor.action;
        const action = buttonActions.get(name);
        if (!action) {
            console.error(`No button action registered for "${name}"`);
            resolved.action = () => { };
        }
        else {
            resolved.action = action;
        }
    }
    for (const key of nestedKeys) {
        resolved[key] = descriptor[key].map(resolveButtonDescriptor);
    }
    return resolved;
}
function resolveButtonsArray(buttons) {
    return Array.isArray(buttons) ? buttons.map(resolveButtonDescriptor) : buttons;
}
function resolveLayoutValue(value) {
    if (Array.isArray(value)) {
        return value.map(resolveLayoutValue);
    }
    if (typeof value !== 'object' || value === null) {
        return value;
    }
    const record = value;
    return 'buttons' in record ? { ...record, buttons: resolveButtonsArray(record.buttons) } : value;
}
export function applyCustomButtonActions(payload) {
    const layout = payload.layout;
    if (typeof layout !== 'object' || layout === null || Array.isArray(layout)) {
        return;
    }
    const record = layout;
    for (const position of Object.keys(record)) {
        record[position] = resolveLayoutValue(record[position]);
    }
}
//# sourceMappingURL=applyCustomButtonActions.js.map