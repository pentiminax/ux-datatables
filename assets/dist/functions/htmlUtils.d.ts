export declare function escapeHtml(value: string): string;
export declare function isRecord(value: unknown): value is Record<string, any>;
export declare function parseBooleanValue(value: unknown, defaultValue?: boolean): boolean;
export declare function isUnsafeUrl(url: string): boolean;
export declare function isSameOriginUrl(url: string): boolean;
export declare function getUrlProtocol(url: string): string | null;
export declare function withDefaultProtocol(url: string, defaultProtocol?: string): string;
export declare function isAllowedUrlProtocol(url: string, allowedProtocols?: string[]): boolean;
