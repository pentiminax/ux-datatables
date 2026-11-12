import type { StyleFramework } from '../types/styleFramework.js';
export interface ConfirmRequest {
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    framework: StyleFramework;
    adapterKey?: string | null;
}
export declare function confirmAction(request: ConfirmRequest): Promise<boolean>;
export declare function confirmBulkAction(...args: Parameters<typeof confirmAction>): Promise<boolean>;
