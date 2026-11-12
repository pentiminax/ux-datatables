export interface MercureConfig {
    hubUrl: string;
    topics: string[];
    protocolVersion?: string;
    withCredentials?: boolean;
    debounceMs?: number;
}
export declare function toUrlPattern(topic: string): string | null;
export declare function createMercureSubscription(config: MercureConfig, onMessage: (event: MessageEvent) => void): EventSource;
