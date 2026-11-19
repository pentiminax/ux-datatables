export type StyleFramework = 'dt' | 'bs5';
export declare const STYLE_FRAMEWORKS: StyleFramework[];
export declare function isStyleFramework(value: unknown): value is StyleFramework;
export declare function registeredStyleFramework(): StyleFramework;
