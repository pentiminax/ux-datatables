import { isRecord } from './htmlUtils.js';
export function restrictSearchBuilder(payload) {
    const raw = payload?.searchBuilder;
    const options = Array.isArray(raw) && raw.length === 0 ? {} : raw;
    if (payload?.serverSide !== true || !isRecord(options)) {
        return;
    }
    payload.searchBuilder = options;
    options.conditions = {
        ...options.conditions,
        array: { ...options.conditions?.array, without: null },
    };
    const offered = options.columns;
    if (offered !== undefined && offered !== '*' && !Array.isArray(offered)) {
        return;
    }
    const columns = payload.columns ?? [];
    options.columns = columns.flatMap((column, index) => column.searchable !== false && (!Array.isArray(offered) || offered.includes(index))
        ? [index]
        : []);
}
//# sourceMappingURL=restrictSearchBuilder.js.map