import type { ColumnStyleAdapter, SwitchRenderOptions } from './ColumnStyleAdapter.js';
export declare class BootstrapColumnStyleAdapter implements ColumnStyleAdapter {
    renderBadge(label: string, variant: string): string;
    renderIcon(iconSvg: string, variant: string, tooltip: string): string;
    renderSwitch(options: SwitchRenderOptions): string;
}
