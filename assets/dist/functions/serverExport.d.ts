export declare const SERVER_EXPORT_ACTION = "ux:export";
export type ProtocolParamsProvider = () => Record<string, unknown>;
type ServerExportConfig = Record<string, unknown> & {
    url?: string;
    exportKey?: string;
    format?: string;
    protocolParams?: ProtocolParamsProvider;
};
export declare function flattenFormValues(data: unknown, prefix?: string, acc?: Array<{
    name: string;
    value: string;
}>): Array<{
    name: string;
    value: string;
}>;
export declare function applyServerExportUrls(payload: Record<string, unknown>, protocolParams?: ProtocolParamsProvider): void;
export declare function resolveExportParams(dt: unknown, config: ServerExportConfig): Record<string, unknown>;
export declare function runServerExport(_e: Event, dt: unknown, _node: HTMLElement, config: Record<string, unknown>): void;
export {};
