export type StyleFramework = 'dt' | 'bs' | 'bs4' | 'bs5';
export interface StyleFrameworkConfig {
    key: StyleFramework;
    cssPattern: string;
}
export declare const STYLE_FRAMEWORKS: StyleFrameworkConfig[];
export declare function isStyleFramework(value: unknown): value is StyleFramework;
