import { createMutationHeaders } from './createMutationHeaders.js';
export async function runBulkAction({ url, dataTable, action, ids, allMatching = false, deselectedIds = [], query = {}, csrfToken, }) {
    const response = await fetch(url, {
        method: 'POST',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({
            dataTable,
            action,
            ids: ids.map(normalizeId),
            allMatching,
            deselectedIds: deselectedIds.map(normalizeId),
            query,
        }),
    });
    if (!response.ok) {
        return { success: false, processed: 0, skipped: 0 };
    }
    const payload = (await response.json());
    return {
        success: payload.success === true,
        processed: typeof payload.processed === 'number' ? payload.processed : 0,
        skipped: typeof payload.skipped === 'number' ? payload.skipped : 0,
        message: typeof payload.message === 'string' ? payload.message : undefined,
    };
}
function normalizeId(id) {
    return id !== '' && !Number.isNaN(Number(id)) ? Number(id) : id;
}
//# sourceMappingURL=runBulkAction.js.map