// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import { detectTheme } from '../detectTheme.js'

afterEach(() => {
    document.documentElement.style.removeProperty('--dt-tw-theme')
})

describe('detectTheme', () => {
    it('returns null when no theme declares itself', () => {
        expect(detectTheme()).toBeNull()
    })

    it('detects the Tailwind theme from the --dt-tw-theme custom property', () => {
        document.documentElement.style.setProperty('--dt-tw-theme', 'tailwind')

        expect(detectTheme()).toBe('tailwind')
    })

    it('tolerates the whitespace a stylesheet declaration leaves around the value', () => {
        document.documentElement.style.setProperty('--dt-tw-theme', ' tailwind ')

        expect(detectTheme()).toBe('tailwind')
    })

    it('ignores an unknown theme name', () => {
        document.documentElement.style.setProperty('--dt-tw-theme', 'daisyui')

        expect(detectTheme()).toBeNull()
    })
})
