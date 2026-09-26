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
    const payload = await readPayload(response);
    if (!response.ok) {
        return {
            success: false,
            processed: 0,
            skipped: 0,
            message: typeof payload.message === 'string' ? payload.message : undefined,
        };
    }
    return {
        success: payload.success === true,
        processed: typeof payload.processed === 'number' ? payload.processed : 0,
        skipped: typeof payload.skipped === 'number' ? payload.skipped : 0,
        message: typeof payload.message === 'string' ? payload.message : undefined,
    };
}
async function readPayload(response) {
    try {
        const payload = await response.json();
        return payload !== null && typeof payload === 'object' ? payload : {};
    }
    catch {
        return {};
    }
}
//# sourceMappingURL=runBulkAction.js.map