import { createMutationHeaders } from './createMutationHeaders.js';
export async function runBulkAction({ url, dataTable, action, ids, allMatching = false, deselectedIds = [], query = {}, csrfToken, }) {
    const response = await fetch(url, {
        method: 'POST',
        headers: createMutationHeaders(csrfToken),
        body: JSON.stringify({
            dataTable,
            action,
            ids,
            allMatching,
            deselectedIds,
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
//# sourceMappingURL=runBulkAction.js.map