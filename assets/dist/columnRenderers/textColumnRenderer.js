import { escapeHtml } from '../functions/htmlUtils.js';
import { isFormatted } from './types.js';
const PLAIN_TEXT_TYPES = new Set(['string', 'string-utf8']);
function hasTypedRenderer(column) {
    const options = column.customOptions ?? {};
    return (true === options.isMoney ||
        true === options.renderAsSwitch ||
        (typeof options.choices === 'object' && options.choices !== null));
}
export const textColumnRenderer = {
    matches(column) {
        return (PLAIN_TEXT_TYPES.has(column?.type) || (isFormatted(column) && hasTypedRenderer(column)));
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