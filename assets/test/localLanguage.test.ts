import { describe, expect, it } from 'vitest'
import { applyLocalLanguage } from '../src/functions/localLanguage'

const cdnUrl = (locale: string) => `https://cdn.datatables.net/plug-ins/3.0.1/i18n/${locale}.json`

describe('applyLocalLanguage', () => {
    it('replaces the fr-FR CDN url with the bundled catalog', async () => {
        const payload: any = { language: { url: cdnUrl('fr-FR') } }

        await applyLocalLanguage(payload)

        expect(payload.language.url).toBeUndefined()
        expect(payload.language.emptyTable).toBe('Aucune donnée disponible dans le tableau')
        expect(payload.language.search).toBe('Rechercher :')
    })

    it('replaces the en-GB CDN url with the bundled catalog', async () => {
        const payload: any = { language: { url: cdnUrl('en-GB') } }

        await applyLocalLanguage(payload)

        expect(payload.language.url).toBeUndefined()
        expect(payload.language.emptyTable).toBe('No data available in table')
    })

    it('matches any DataTables version in the url', async () => {
        const payload: any = {
            language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/fr-FR.json' },
        }

        await applyLocalLanguage(payload)

        expect(payload.language.url).toBeUndefined()
        expect(payload.language.processing).toBe('Traitement...')
    })

    it('keeps the CDN url for locales without a bundled catalog', async () => {
        const payload: any = { language: { url: cdnUrl('de-DE') } }

        await applyLocalLanguage(payload)

        expect(payload.language).toEqual({ url: cdnUrl('de-DE') })
    })

    it('leaves an inline language object untouched', async () => {
        const payload: any = { language: { emptyTable: 'Custom' } }

        await applyLocalLanguage(payload)

        expect(payload.language).toEqual({ emptyTable: 'Custom' })
    })

    it('does nothing when the payload has no language option', async () => {
        const payload: any = { columns: [] }

        await applyLocalLanguage(payload)

        expect(payload).toEqual({ columns: [] })
    })
})
