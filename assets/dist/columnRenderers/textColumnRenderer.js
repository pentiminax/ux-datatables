import { escapeHtml } from '../functions/htmlUtils.js';
import { isFormatted } from './types.js';
const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8']);
const HTML_TYPES = new Set(['html', 'html-utf8', 'html-num', 'html-num-fmt']);
function isSpecializedHtmlColumn(column) {
    const options = column.customOptions ?? {};
    return (true === options.isEmail ||
        true === options.isUrl ||
        true === options.isIcon ||
        true === options.isImage ||
        (typeof options.choices === 'object' && options.choices !== null));
}
function isFormattedText(column) {
    if (!isFormatted(column)) {
        return false;
    }
    return !HTML_TYPES.has(column.type) || isSpecializedHtmlColumn(column);
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