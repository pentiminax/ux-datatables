import { createMutationHeaders } from './createMutationHeaders.js'

export interface BulkActionRequest {
    url: string
    dataTable: string
    action: string
    ids: string[]
    allMatching?: boolean
    deselectedIds?: string[]
    query?: Record<string, unknown>
    csrfToken?: string
}

export interface BulkActionResult {
    success: boolean
    processed: number
    skipped: number
    message?: string
}

/**
 * The signed action token travels in the body, never in the URL: a query string leaks through
 * access logs and the Referer header.
 */
export async function runBulkAction({
    url,
    dataTable,
    action,
    ids,
    allMatching = false,
    deselectedIds = [],
    query = {},
    csrfToken,
}: BulkActionRequest): Promise<BulkActionResult> {
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
    })

    const payload = await readPayload(response)

    if (!response.ok) {
        return {
            success: false,
            processed: 0,
            skipped: 0,
            message: typeof payload.message === 'string' ? payload.message : undefined,
        }
    }

    return {
        success: payload.success === true,
        processed: typeof payload.processed === 'number' ? payload.processed : 0,
        skipped: typeof payload.skipped === 'number' ? payload.skipped : 0,
        message: typeof payload.message === 'string' ? payload.message : undefined,
    }
}

/**
 * The bundle answers a refused run with a client-safe `message`; anything in front of it (a
 * firewall, a proxy) may answer with HTML instead, which carries nothing to show.
 */
async function readPayload(response: Response): Promise<Partial<BulkActionResult>> {
    try {
        const payload = await response.json()

        return payload !== null && typeof payload === 'object' ? payload : {}
    } catch {
        return {}
    }
}
