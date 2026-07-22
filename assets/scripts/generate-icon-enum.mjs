// Generates src/Enum/Icon.php from the installed lucide package.
// Re-run after bumping lucide: `node assets/scripts/generate-icon-enum.mjs`
import { writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'
import * as lucide from 'lucide'

const require = createRequire(import.meta.url)
const lucideVersion = require('lucide/package.json').version

const here = dirname(fileURLToPath(import.meta.url))
const outFile = resolve(here, '../../src/Enum/Icon.php')

// lucide.icons keys are PascalCase (e.g. "CircleCheck", "AArrowDown").
const pascalNames = Object.keys(lucide.icons ?? {}).sort()

const pascalToKebab = (name) =>
    name
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase()

// PHP identifiers cannot start with a digit; prefix an underscore if needed.
const pascalToConst = (pascal) => (/^[0-9]/.test(pascal) ? `_${pascal}` : pascal)

const seenValues = new Set()
const cases = []
for (const pascal of pascalNames) {
    const value = pascalToKebab(pascal)
    // lucide ships casing aliases (ArrowDownAZ / ArrowDownAz) that collapse to the
    // same kebab value; a backed enum forbids duplicate values, so keep the first.
    if (seenValues.has(value)) {
        continue
    }
    seenValues.add(value)
    cases.push(`    case ${pascalToConst(pascal)} = '${value}';`)
}

const php = `<?php

declare(strict_types=1);

namespace Pentiminax\\UX\\DataTables\\Enum;

enum Icon: string
{
${cases.join('\n')}
}
`

writeFileSync(outFile, php)
console.log(`Wrote ${cases.length} cases to ${outFile} (lucide ${lucideVersion})`)
