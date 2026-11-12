import type { ColumnStyleAdapter } from '../columnStyles/ColumnStyleAdapter.js';
import type { ColumnRenderer } from './types.js';
export declare function createBooleanColumnRenderer(toggleUrl: string, mutationsEnabled: boolean | undefined, style: ColumnStyleAdapter): ColumnRenderer;
