import { readFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const SOURCE_ROOT = join(__dirname, '..')
const SPECIFIER_PATTERNS = [
    /^(?:import|export)(?! type)[^'"]*?\bfrom\s*'([^']+)'/gm,
    /^import\s*'([^']+)'/gm,
    /(?<!typeof )\bimport\(\s*'([^']+)'\s*\)/g,
]

function bareSpecifiersReachableFrom(entry: string): Set<string> {
    const bare = new Set<string>()
    const visited = new Set<string>()
    const queue = [join(SOURCE_ROOT, entry)]

    while (queue.length > 0) {
        const file = queue.pop() as string

        if (visited.has(file)) {
            continue
        }

        visited.add(file)

        const contents = readFileSync(file, 'utf8')
        const specifiers = SPECIFIER_PATTERNS.flatMap((pattern) =>
            [...contents.matchAll(pattern)].map(([, specifier]) => specifier)
        )

        for (const specifier of specifiers) {
            if (specifier.startsWith('.')) {
                queue.push(resolve(dirname(file), specifier.replace(/\.js$/, '.ts')))
            } else {
                bare.add(specifier)
            }
        }
    }

    return bare
}

describe('core entry', () => {
    it('reaches no optional package, so bundlers and import maps only need Stimulus', () => {
        expect([...bareSpecifiersReachableFrom('core.ts')]).toEqual(['@hotwired/stimulus'])
    })

    it('keeps the built-in loaders reachable from the 1.x controller entry', () => {
        expect(bareSpecifiersReachableFrom('controller.ts')).toContain('datatables.net-select-bs5')
    })
})
