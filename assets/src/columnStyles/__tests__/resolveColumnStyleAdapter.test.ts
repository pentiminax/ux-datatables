import { describe, expect, it } from 'vitest'
import { BootstrapColumnStyleAdapter } from '../BootstrapColumnStyleAdapter.js'
import { resolveColumnStyleAdapter } from '../resolveColumnStyleAdapter.js'
import { TailwindColumnStyleAdapter } from '../TailwindColumnStyleAdapter.js'

describe('resolveColumnStyleAdapter', () => {
    it.each([
        'bs',
        'bs4',
        'bs5',
    ] as const)('resolves the Bootstrap adapter for the %s framework', (framework) => {
        expect(resolveColumnStyleAdapter(framework)).toBeInstanceOf(BootstrapColumnStyleAdapter)
    })

    it.each([
        'dt',
        'bm',
        'zf',
        'jqui',
        'se',
    ] as const)('resolves the Tailwind adapter for the %s framework', (framework) => {
        expect(resolveColumnStyleAdapter(framework)).toBeInstanceOf(TailwindColumnStyleAdapter)
    })
})
