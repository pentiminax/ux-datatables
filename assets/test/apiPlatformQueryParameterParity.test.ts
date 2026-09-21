import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { ApiPlatformAdapter } from '../src/functions/apiPlatformAdapter'

/**
 * Replays the shared translation cases the PHP suite replays in
 * tests/Unit/ApiPlatform/ApiPlatformQueryParameterSharedCasesTest.php. A case failing on one side
 * only means the two implementations have drifted: a table read server-side would then query the
 * API differently from the same table read by the browser.
 */
type RequestParams = Parameters<ApiPlatformAdapter['buildRequestParams']>[0]

interface SharedCase {
    name: string
    params: RequestParams
    expected: Record<string, string>
}

interface SharedFixture {
    columns: { name: string; data: string; field: string }[]
    cases: SharedCase[]
}

const fixture: SharedFixture = JSON.parse(
    // Vitest runs from assets/, so the PHP fixture sits one directory up.
    readFileSync(
        resolve(process.cwd(), '../tests/Fixtures/api-platform-query-parameters.json'),
        'utf8'
    )
)

describe('DataTables to API Platform query translation', () => {
    const adapter = new ApiPlatformAdapter(fixture.columns)

    for (const sharedCase of fixture.cases) {
        it(sharedCase.name, () => {
            expect(adapter.buildRequestParams(sharedCase.params)).toEqual(sharedCase.expected)
        })
    }
})
