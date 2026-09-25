export type ButtonAction = (
    e: Event,
    dt: unknown,
    node: HTMLElement,
    config: Record<string, unknown>
) => void

export class ButtonActionRegistry {
    private readonly actions = new Map<string, ButtonAction>()

    register(name: string, action: ButtonAction): this {
        this.actions.set(name, action)

        return this
    }

    get(name: string): ButtonAction | null {
        return this.actions.get(name) ?? null
    }
}

export const buttonActions = new ButtonActionRegistry()
