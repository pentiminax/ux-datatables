import type { StyleFramework } from '../types/styleFramework.js';
import type { ModalAdapter } from './ModalAdapter.js';
export declare function resolveModalAdapter(explicitKey: string | null, framework: StyleFramework): Promise<ModalAdapter | null>;
