export function configureState(payload) {
    if (!payload.state)
        return;
    const state = payload.state;
    payload.stateSave = true;
    if (state.duration !== undefined) {
        payload.stateDuration = state.duration;
    }
    delete payload.state;
}
//# sourceMappingURL=configureState.js.map