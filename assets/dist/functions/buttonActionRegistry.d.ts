export type ButtonAction = (e: Event, dt: unknown, node: HTMLElement, config: Record<string, unknown>) => void;
export declare class ButtonActionRegistry {
    private readonly actions;
    register(name: string, action: ButtonAction): this;
    get(name: string): ButtonAction | null;
}
export declare const buttonActions: ButtonActionRegistry;
