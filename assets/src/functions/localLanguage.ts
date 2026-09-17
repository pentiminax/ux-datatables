type CatalogLoader = () => Promise<{ default: Record<string, unknown> }>

const catalogs: Record<string, CatalogLoader> = {
    'en-GB': () => import('../i18n/en-GB.js'),
    'fr-FR': () => import('../i18n/fr-FR.js'),
}

const CDN_LOCALE_PATTERN = /\/i18n\/([\w-]+)\.json(?:[?#].*)?$/

export async function applyLocalLanguage(payload: any): Promise<void> {
    const url = payload?.language?.url

    if (typeof url !== 'string') {
        return
    }

    const locale = CDN_LOCALE_PATTERN.exec(url)?.[1]
    const loader = locale ? catalogs[locale] : undefined

    if (!loader) {
        return
    }

    payload.language = { ...(await loader()).default }
}
