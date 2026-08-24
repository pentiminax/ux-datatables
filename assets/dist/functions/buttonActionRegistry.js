export class ButtonActionRegistry {
    constructor() {
        this.actions = new Map();
    }
    register(name, action) {
        this.actions.set(name, action);
        return this;
    }
    get(name) {
        return this.actions.get(name) ?? null;
    }
}
export const buttonActions = new ButtonActionRegistry();
//# sourceMappingURL=buttonActionRegistry.js.map