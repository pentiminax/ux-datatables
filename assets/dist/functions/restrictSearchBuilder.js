export function restrictSearchBuilder(payload) {
    const options = payload?.searchBuilder;
    if (payload?.serverSide !== true || !options || typeof options !== 'object') {
        return;
    }
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