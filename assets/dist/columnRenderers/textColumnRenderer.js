import { escapeHtml } from '../functions/htmlUtils.js';
const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8']);
export const textColumnRenderer = {
    matches(column) {
        return PLAIN_TEXT_TYPES.has(column?.type);
    },
    configure(column) {
        if (typeof column.render === 'function') {
            return;
        }
        column.render = (data, type) => {
            if (type !== 'display') {
                return data;
            }
            if (data === null || data === undefined) {
                return data;
            }
            return escapeHtml(String(data));
        };
    },
};
//# sourceMappingURL=textColumnRenderer.js.map