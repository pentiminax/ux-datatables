import { buttonActions } from './buttonActionRegistry.js';
function resolveButtonDescriptor(button) {
    if (typeof button !== 'object' || button === null || Array.isArray(button)) {
        return button;
    }
    const descriptor = button;
    if (typeof descriptor.action !== 'string') {
        return button;
    }
    const action = buttonActions.get(descriptor.action);
    if (!action) {
        console.error(`No button action registered for "${descriptor.action}"`);
        return { ...descriptor, action: () => { } };
    }
    return { ...descriptor, action };
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