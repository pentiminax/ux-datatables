import { isRecord } from './htmlUtils.js';
export function forwardedQuery(payload) {
    return isRecord(payload.forwardedQuery) ? payload.forwardedQuery : {};
}
//# sourceMappingURL=forwardedQuery.js.map