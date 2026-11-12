export interface AjaxActionOptions {
    button: HTMLElement;
    method: string;
    url: string;
    token: string;
    dispatch: (name: string, detail: Record<string, unknown>) => void;
    navigate: (url: string) => void;
    reload: () => void;
}
export declare function runAjaxAction({ button, method, url, token, dispatch, navigate, reload, }: AjaxActionOptions): Promise<void>;
