import { escapeHtml } from '../functions/htmlUtils.js';
import { isFormatted } from './types.js';
const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8']);
const HTML_TYPES = new Set(['html', 'html-utf8', 'html-num', 'html-num-fmt']);
function isFormattedText(column) {
    if (!isFormatted(column)) {
        return false;
    }
    const choices = column.customOptions?.choices;
    return !HTML_TYPES.has(column.type) || (typeof choices === 'object' && choices !== null);
}
export const textColumnRenderer = {
    matches(column) {
        return PLAIN_TEXT_TYPES.has(column?.type) || isFormattedText(column);
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