import { describe, expect, it } from 'vitest'
import { BootstrapColumnStyleAdapter } from '../BootstrapColumnStyleAdapter.js'
import { TailwindThemeColumnStyleAdapter } from '../TailwindThemeColumnStyleAdapter.js'
import { resolveColumnStyleAdapter } from '../resolveColumnStyleAdapter.js'
import { TailwindColumnStyleAdapter } from '../TailwindColumnStyleAdapter.js'

describe('resolveColumnStyleAdapter', () => {
    it.each(['bs', 'bs4', 'bs5'] as const)(
        'resolves the Bootstrap adapter for the %s framework',
        (framework) => {
            expect(resolveColumnStyleAdapter(framework)).toBeInstanceOf(BootstrapColumnStyleAdapter)
        }
    )

    it.each(['dt', 'zf', 'jqui', 'se'] as const)(
        'resolves the Tailwind adapter for the %s framework',
        (framework) => {
            expect(resolveColumnStyleAdapter(framework)).toBeInstanceOf(TailwindColumnStyleAdapter)
        }
    )

    it.each(['bs5', 'dt', 'zf'] as const)(
        'resolves the theme adapter over the %s framework default',
        (framework) => {
            expect(resolveColumnStyleAdapter(framework, 'tailwind')).toBeInstanceOf(
                TailwindThemeColumnStyleAdapter
            )
        }
    )

    it('keeps the framework default when no theme is active', () => {
        expect(resolveColumnStyleAdapter('bs5', null)).toBeInstanceOf(BootstrapColumnStyleAdapter)
    })
})
