export function configureState(payload: Record<string, any>): void {
    if (!payload.state) return
    const state = payload.state
    payload.stateSave = true
    if (state.duration !== undefined) {
        payload.stateDuration = state.duration
    }
    delete payload.state
}