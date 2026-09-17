import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const SOURCE_ROOT = join(__dirname, '..')
const SPECIFIER_PATTERN = /(?:\bfrom|\bimport)\s*\(?\s*'([^']+)'/g
const DYNAMIC_IMPORT_PATTERN = /\bimport\s*\(\s*(?:\/\*[^*]*\*\/\s*)*([^'\s)])/g

function listSourceFiles(directory: string): string[] {
    return readdirSync(directory).flatMap((entry) => {
        const path = join(directory, entry)

        if (statSync(path).isDirectory()) {
            return entry === '__tests__' ? [] : listSourceFiles(path)
        }

        return path.endsWith('.ts') ? [path] : []
    })
}

function isSubpathSpecifier(specifier: string): boolean {
    if (specifier.startsWith('.')) {
        return true
    }

    return specifier.replace(/^@[^/]+\//, '').includes('/')
}

describe('published import specifiers', () => {
    it('are fully specified so bundlers can resolve them from a "type": "module" package', () => {
        const offenders: string[] = []

        for (const file of listSourceFiles(SOURCE_ROOT)) {
            const contents = readFileSync(file, 'utf8')

            for (const [, specifier] of contents.matchAll(SPECIFIER_PATTERN)) {
                if (isSubpathSpecifier(specifier) && !/\.[a-z0-9]+$/.test(specifier)) {
                    offenders.push(`${file}: ${specifier}`)
                }
            }
        }

        expect(offenders).toEqual([])
    })

    it('never computes a dynamic import specifier, which no bundler can resolve statically', () => {
        const offenders: string[] = []

        for (const file of listSourceFiles(SOURCE_ROOT)) {
            const contents = readFileSync(file, 'utf8')

            for (const [match] of contents.matchAll(DYNAMIC_IMPORT_PATTERN)) {
                offenders.push(`${file}: ${match.trim()}`)
            }
        }

        expect(offenders).toEqual([])
    })
})
